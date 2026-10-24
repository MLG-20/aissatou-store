<?php

namespace App\Filament\Resources\Catalogue;

use App\Enums\Admin\AdminPermission;
use App\Enums\Catalogue\StockMovementType;
use App\Filament\Clusters\Stock;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Catalogue\InventoryResource\Pages;
use App\Filament\Resources\Order\OrderResource;
use App\Models\Admin;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\ProductVariant;
use App\Models\Catalogue\StockMovement;
use App\Services\StockService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Inventaire (Phase 6B) : toutes les déclinaisons, par catégorie, avec leur
 * stock, leur seuil et leur état. Le stock ne se modifie ici que par
 * « Ajuster le stock » (nouvelle quantité + motif, auteur tracé dans le
 * journal des mouvements), depuis la liste ou la fiche de la déclinaison,
 * qui raconte aussi son historique. Les produits SANS déclinaison n'ont pas de stock
 * suivi (décision Ahmed) : ils sont signalés en tête de page.
 */
class InventoryResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Stock;
    }

    protected static ?string $model = ProductVariant::class;

    protected static ?string $cluster = Stock::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $slug = 'inventaire';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Inventaire';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'déclinaison';
    }

    public static function getPluralModelLabel(): string
    {
        return 'inventaire';
    }

    /** Déclinaisons vendues (actives, produit actif) au seuil ou en dessous. */
    public static function getNavigationBadge(): ?string
    {
        $low = static::sellable(ProductVariant::query())->lowStock()->count();

        return $low > 0 ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Déclinaisons en stock faible ou en rupture';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('product.category');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('Produit')->searchable()->sortable(),
                TextColumn::make('attributes')
                    ->label('Déclinaison')
                    ->getStateUsing(fn (ProductVariant $record): string => self::attributesLabel($record)),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn (ProductVariant $record): string => self::stockColor($record)),
                // Liste volontairement courte : référence, seuil et
                // historique sont sur la fiche de la déclinaison.
                TextColumn::make('state')
                    ->label('État')
                    ->badge()
                    ->getStateUsing(fn (ProductVariant $record): string => self::state($record))
                    ->color(fn (string $state): string => self::stateColor($state)),
            ])
            ->defaultGroup(
                Group::make('product.category.name')
                    ->label('Catégorie')
                    ->collapsible()
            )
            ->defaultSort('stock')
            ->filters([
                SelectFilter::make('category')
                    ->label('Catégorie')
                    ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('product', fn (Builder $product) => $product->where('category_id', $data['value']))
                        : $query),
                Filter::make('low')
                    ->label('Stock faible ou rupture uniquement')
                    ->query(fn (Builder $query): Builder => $query->lowStock()),
                TernaryFilter::make('sellable')
                    ->label('En vente')
                    ->placeholder('Toutes')
                    ->trueLabel('En vente uniquement')
                    ->falseLabel('Non vendues uniquement')
                    ->queries(
                        true: fn (Builder $query): Builder => static::sellable($query),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q
                            ->where('is_active', false)
                            ->orWhereHas('product', fn (Builder $p) => $p->where('is_active', false))),
                    )
                    ->default(true),
            ])
            ->actions([
                ViewAction::make()->label('Voir la fiche'),
                Action::make('adjust')
                    ->label('Ajuster le stock')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->modalHeading(fn (ProductVariant $record): string => 'Ajuster : '.$record->label())
                    ->modalDescription(fn (ProductVariant $record): string => self::adjustDescription($record))
                    ->form(self::adjustForm())
                    ->action(fn (ProductVariant $record, array $data) => self::adjust($record, $data)),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Résumé')
                    ->schema([
                        TextEntry::make('product.name')
                            ->label('Produit')
                            ->url(fn (ProductVariant $record): ?string => $record->product
                                ? ProductResource::getUrl('edit', ['record' => $record->product])
                                : null)
                            ->color('primary'),
                        TextEntry::make('variant')
                            ->label('Déclinaison')
                            ->getStateUsing(fn (ProductVariant $record): string => self::attributesLabel($record)),
                        TextEntry::make('sku')->label('Référence')->copyable(),
                        TextEntry::make('state')
                            ->label('État')
                            ->badge()
                            ->getStateUsing(fn (ProductVariant $record): string => self::state($record))
                            ->color(fn (string $state): string => self::stateColor($state)),
                        TextEntry::make('stock')
                            ->label('Stock actuel')
                            ->weight('bold')
                            ->color(fn (ProductVariant $record): string => self::stockColor($record)),
                        TextEntry::make('threshold')
                            ->label('Alerte stock faible à partir de')
                            ->getStateUsing(fn (ProductVariant $record): string => $record->lowStockThreshold()
                                .($record->low_stock_threshold === null ? ' (réglage général)' : ' (propre à cet article)')),
                    ])
                    ->columns(3),

                Section::make('Historique du stock')
                    ->description('Les 50 derniers mouvements, du plus récent au plus ancien. L\'onglet « Mouvements » liste tout.')
                    ->schema([
                        RepeatableEntry::make('latestMovements')
                            ->label('')
                            ->placeholder('Aucun mouvement enregistré.')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Le')
                                    ->dateTime('d/m/Y à H:i'),
                                TextEntry::make('type')
                                    ->label('Mouvement')
                                    ->badge()
                                    ->formatStateUsing(fn (StockMovementType $state): string => $state->label())
                                    ->color(fn (StockMovementType $state): string => $state->color()),
                                TextEntry::make('quantity')
                                    ->label('Quantité')
                                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),
                                TextEntry::make('stock_after')
                                    ->label('Stock restant'),
                                TextEntry::make('reason')
                                    ->label('Motif')
                                    ->placeholder('—'),
                                TextEntry::make('admin_name')
                                    ->label('Par')
                                    ->placeholder('client / système'),
                                TextEntry::make('order.number')
                                    ->label('Commande')
                                    ->placeholder('—')
                                    ->url(fn (StockMovement $record): ?string => $record->order && self::canSeeOrders()
                                        ? OrderResource::getUrl('view', ['record' => $record->order])
                                        : null),
                            ])
                            ->columns(7),
                    ]),
            ]);
    }

    /** @return array<int, Component> */
    public static function adjustForm(): array
    {
        return [
            TextInput::make('stock')
                ->label('Nouvelle quantité en stock')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->required()
                ->default(fn (ProductVariant $record): int => $record->stock),
            Textarea::make('reason')
                ->label('Motif')
                ->placeholder('Ex. : réassort fournisseur, inventaire physique, article abîmé…')
                ->required()
                ->minLength(3)
                ->maxLength(255)
                ->rows(2),
        ];
    }

    public static function adjustDescription(ProductVariant $record): string
    {
        return 'Stock actuel : '.$record->stock.'. Le mouvement sera journalisé à votre nom avec le motif.';
    }

    /** Ajustement depuis la liste ou la fiche : permission revérifiée à l'exécution. */
    public static function adjust(ProductVariant $record, array $data): void
    {
        $admin = filament()->auth()->user();
        abort_unless($admin instanceof Admin && $admin->hasAdminPermission(AdminPermission::Stock), 403);

        $movement = app(StockService::class)->adjust($record, (int) $data['stock'], $data['reason'], $admin);

        Notification::make()
            ->success()
            ->title('Stock ajusté : '.($movement->quantity > 0 ? '+' : '').$movement->quantity.' → '.$movement->stock_after)
            ->send();
    }

    private static function attributesLabel(ProductVariant $record): string
    {
        return collect($record->getAttribute('attributes') ?? [])
            ->map(fn ($value, $key) => "{$key} : {$value}")
            ->implode(', ') ?: '—';
    }

    private static function state(ProductVariant $record): string
    {
        return match (true) {
            ! $record->is_active || ! $record->product?->is_active => 'Non vendue',
            $record->stock === 0 => 'Rupture',
            $record->isLowStock() => 'Stock faible',
            default => 'OK',
        };
    }

    private static function stateColor(string $state): string
    {
        return match ($state) {
            'Rupture' => 'danger',
            'Stock faible' => 'warning',
            'Non vendue' => 'gray',
            default => 'success',
        };
    }

    private static function stockColor(ProductVariant $record): string
    {
        return match (true) {
            $record->stock === 0 => 'danger',
            $record->isLowStock() => 'warning',
            default => 'success',
        };
    }

    private static function canSeeOrders(): bool
    {
        $admin = filament()->auth()->user();

        return $admin instanceof Admin && $admin->hasAdminPermission(AdminPermission::Orders);
    }

    /** Déclinaison réellement en vente : active ET produit actif. */
    public static function sellable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('product', fn (Builder $product) => $product->where('is_active', true));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventory::route('/'),
            'view' => Pages\ViewInventory::route('/{record}'),
        ];
    }
}

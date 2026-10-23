<?php

namespace App\Filament\Resources\Catalogue;

use App\Enums\Admin\AdminPermission;
use App\Enums\Catalogue\StockMovementType;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Catalogue\StockMovementResource\Pages;
use App\Filament\Resources\Order\OrderResource;
use App\Models\Admin;
use App\Models\Catalogue\StockMovement;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Historique des mouvements de stock (Phase 6B) : qui, quand, combien,
 * pourquoi, stock résultant. Lecture seule — journal immuable.
 */
class StockMovementResource extends Resource
{
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Stock;
    }

    protected static ?string $model = StockMovement::class;

    protected static ?string $slug = 'mouvements-de-stock';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Stock';

    protected static ?string $navigationLabel = 'Mouvements de stock';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'mouvement de stock';
    }

    public static function getPluralModelLabel(): string
    {
        return 'mouvements de stock';
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('label')->label('Article')->searchable()->wrap(),
                TextColumn::make('sku')->label('Réf.')->searchable()->toggleable(),
                TextColumn::make('type')
                    ->label('Mouvement')
                    ->badge()
                    ->formatStateUsing(fn (StockMovementType $state): string => $state->label())
                    ->color(fn (StockMovementType $state): string => match ($state) {
                        StockMovementType::Sale => 'gray',
                        StockMovementType::Cancellation => 'info',
                        StockMovementType::Adjustment => 'warning',
                        StockMovementType::Initial => 'success',
                    }),
                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('stock_after')->label('Stock après'),
                TextColumn::make('reason')->label('Motif')->placeholder('—')->wrap(),
                TextColumn::make('admin_name')->label('Par')->placeholder('client / système'),
                // Le gestionnaire a accès aux commandes : lien direct si la
                // permission est là, simple numéro sinon.
                TextColumn::make('order.number')
                    ->label('Commande')
                    ->placeholder('—')
                    ->url(fn (StockMovement $record): ?string => $record->order && self::canSeeOrders()
                        ? OrderResource::getUrl('view', ['record' => $record->order])
                        : null),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Mouvement')->options(StockMovementType::options()),
                SelectFilter::make('variant')
                    ->label('Déclinaison')
                    ->attribute('product_variant_id')
                    ->relationship('variant', 'sku')
                    ->searchable(),
                SelectFilter::make('admin_id')->label('Par')->relationship('admin', 'name'),
            ]);
    }

    private static function canSeeOrders(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof Admin && $admin->hasAdminPermission(AdminPermission::Orders);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}

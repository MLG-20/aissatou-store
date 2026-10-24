<?php

namespace App\Filament\Resources\Catalogue;

use App\Enums\Admin\AdminPermission;
use App\Enums\Catalogue\StockMovementType;
use App\Filament\Clusters\Stock;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Catalogue\StockMovementResource\Pages;
use App\Models\Catalogue\StockMovement;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Onglet « Mouvements » du stock (Phase 6B) : tout ce qui a bougé, en bref ;
 * le détail (motif, auteur, commande) est sur la fiche de la déclinaison.
 * Lecture seule — journal immuable.
 */
class StockMovementResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Stock;
    }

    protected static ?string $model = StockMovement::class;

    protected static ?string $cluster = Stock::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $slug = 'mouvements';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Mouvements';

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
                TextColumn::make('type')
                    ->label('Mouvement')
                    ->badge()
                    ->formatStateUsing(fn (StockMovementType $state): string => $state->label())
                    ->color(fn (StockMovementType $state): string => $state->color()),
                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),
                // Motif, auteur et commande : sur la fiche de la déclinaison,
                // ouverte au clic sur la ligne.
                TextColumn::make('stock_after')->label('Stock après'),
            ])
            ->defaultSort('id', 'desc')
            // Article supprimé depuis : pas de fiche, la ligne reste lisible.
            ->recordUrl(fn (StockMovement $record): ?string => $record->product_variant_id
                ? InventoryResource::getUrl('view', ['record' => $record->product_variant_id])
                : null)
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}

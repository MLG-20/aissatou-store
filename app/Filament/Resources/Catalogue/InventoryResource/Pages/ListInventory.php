<?php

namespace App\Filament\Resources\Catalogue\InventoryResource\Pages;

use App\Filament\Resources\Catalogue\InventoryResource;
use App\Filament\Resources\Catalogue\InventoryResource\Widgets\StockByCategory;
use App\Models\Catalogue\Product;
use Filament\Resources\Pages\ListRecords;

class ListInventory extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected static ?string $title = 'Inventaire';

    /** Produits sans déclinaison : stock non suivi (décision Ahmed, point 8). */
    public function getSubheading(): ?string
    {
        $untracked = Product::query()->where('is_active', true)->whereDoesntHave('variants')->count();

        return $untracked > 0
            ? $untracked.' produit(s) en vente sans déclinaison : stock non suivi (ajoutez une déclinaison pour le suivre).'
            : null;
    }

    protected function getHeaderWidgets(): array
    {
        return [StockByCategory::class];
    }
}

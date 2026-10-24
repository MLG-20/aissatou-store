<?php

namespace App\Filament\Resources\Catalogue\InventoryResource\Pages;

use App\Filament\Resources\Catalogue\InventoryResource;
use App\Models\Catalogue\ProductVariant;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInventory extends ViewRecord
{
    protected static string $resource = InventoryResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjust')
                ->label('Ajuster le stock')
                ->icon('heroicon-o-adjustments-horizontal')
                ->modalHeading(fn (ProductVariant $record): string => 'Ajuster : '.$record->label())
                ->modalDescription(fn (ProductVariant $record): string => InventoryResource::adjustDescription($record))
                ->form(InventoryResource::adjustForm())
                ->action(fn (ProductVariant $record, array $data) => InventoryResource::adjust($record, $data)),
        ];
    }
}

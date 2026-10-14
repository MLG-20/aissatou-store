<?php

namespace App\Filament\Resources\Delivery\DeliveryZoneResource\Pages;

use App\Filament\Resources\Delivery\DeliveryZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeliveryZones extends ListRecords
{
    protected static string $resource = DeliveryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

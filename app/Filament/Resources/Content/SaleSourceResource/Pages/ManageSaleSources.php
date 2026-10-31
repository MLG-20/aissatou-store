<?php

namespace App\Filament\Resources\Content\SaleSourceResource\Pages;

use App\Filament\Resources\Content\SaleSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSaleSources extends ManageRecords
{
    protected static string $resource = SaleSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

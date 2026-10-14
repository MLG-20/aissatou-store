<?php

namespace App\Filament\Resources\Catalogue\MaterialResource\Pages;

use App\Filament\Resources\Catalogue\MaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterials extends ListRecords
{
    protected static string $resource = MaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

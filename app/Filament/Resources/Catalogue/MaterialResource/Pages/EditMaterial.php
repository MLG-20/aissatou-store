<?php

namespace App\Filament\Resources\Catalogue\MaterialResource\Pages;

use App\Filament\Resources\Catalogue\MaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterial extends EditRecord
{
    protected static string $resource = MaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

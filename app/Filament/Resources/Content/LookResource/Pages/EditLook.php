<?php

namespace App\Filament\Resources\Content\LookResource\Pages;

use App\Filament\Resources\Content\LookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLook extends EditRecord
{
    protected static string $resource = LookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

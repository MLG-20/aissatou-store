<?php

namespace App\Filament\Resources\Content\CookieDeclarationResource\Pages;

use App\Filament\Resources\Content\CookieDeclarationResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCookieDeclarations extends ManageRecords
{
    protected static string $resource = CookieDeclarationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

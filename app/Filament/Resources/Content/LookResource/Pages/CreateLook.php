<?php

namespace App\Filament\Resources\Content\LookResource\Pages;

use App\Filament\Resources\Content\LookResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLook extends CreateRecord
{
    protected static string $resource = LookResource::class;

    // Les points se placent sur la photo enregistrée : on enchaîne donc sur
    // la page de modification, où l'aperçu est disponible.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}

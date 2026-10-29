<?php

namespace App\Filament\Resources\Content\LegalPageResource\Pages;

use App\Filament\Resources\Content\LegalPageResource;
use App\Services\AdminAudit;
use Filament\Resources\Pages\EditRecord;

class EditLegalPage extends EditRecord
{
    protected static string $resource = LegalPageResource::class;

    /** Un texte légal qui change engage la boutique : on garde la trace de qui l'a modifié. */
    protected function afterSave(): void
    {
        AdminAudit::record('legal_page.updated', $this->record, label: $this->record->title);
    }
}

<?php

namespace App\Filament\Resources\Admin\AdminActivityResource\Pages;

use App\Filament\Resources\Admin\AdminActivityResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAdminActivity extends ViewRecord
{
    protected static string $resource = AdminActivityResource::class;

    public function getTitle(): string
    {
        return 'Activité du '.$this->getRecord()->created_at->format('d/m/Y à H:i');
    }
}

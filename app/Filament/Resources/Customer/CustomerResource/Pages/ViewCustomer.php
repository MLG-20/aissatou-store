<?php

namespace App\Filament\Resources\Customer\CustomerResource\Pages;

use App\Filament\Resources\Customer\CustomerResource;
use App\Services\AdminAudit;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * Consultation de données personnelles (habitudes d'achat) : tracée avec
     * l'admin précis, une fois par ouverture de fiche (Phase 6E).
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        AdminAudit::record('customer.viewed', $this->getRecord(), label: $this->getRecord()->email);
    }
}

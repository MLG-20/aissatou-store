<?php

namespace App\Filament\Resources\Order\PromoCodeResource\Pages;

use App\Filament\Resources\Order\PromoCodeResource;
use App\Services\AdminAudit;
use Filament\Resources\Pages\CreateRecord;

class CreatePromoCode extends CreateRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = filament()->auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        AdminAudit::record('promo.created', $this->getRecord(), [
            'reduction' => $this->getRecord()->valueLabel(),
        ], label: $this->getRecord()->code);
    }
}

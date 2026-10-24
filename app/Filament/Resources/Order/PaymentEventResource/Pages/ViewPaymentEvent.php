<?php

namespace App\Filament\Resources\Order\PaymentEventResource\Pages;

use App\Filament\Resources\Order\PaymentEventResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentEvent extends ViewRecord
{
    protected static string $resource = PaymentEventResource::class;

    public function getTitle(): string
    {
        return 'Fiche du message suspect';
    }
}

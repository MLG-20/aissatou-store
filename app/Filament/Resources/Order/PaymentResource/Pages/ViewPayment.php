<?php

namespace App\Filament\Resources\Order\PaymentResource\Pages;

use App\Filament\Resources\Order\PaymentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        return 'Paiement de la commande '.$this->getRecord()->order->number;
    }
}

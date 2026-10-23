<?php

namespace App\Filament\Resources\Order\PaymentEventResource\Pages;

use App\Filament\Resources\Order\PaymentEventResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentEvents extends ListRecords
{
    protected static string $resource = PaymentEventResource::class;
}

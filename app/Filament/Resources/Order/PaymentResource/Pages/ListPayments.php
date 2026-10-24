<?php

namespace App\Filament\Resources\Order\PaymentResource\Pages;

use App\Filament\Resources\Order\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}

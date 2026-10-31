<?php

namespace App\Filament\Resources\Delivery\CourierResource\Pages;

use App\Filament\Resources\Delivery\CourierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourier extends EditRecord
{
    protected static string $resource = CourierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => $this->record->orders()->doesntExist()),
        ];
    }
}

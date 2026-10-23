<?php

namespace App\Filament\Resources\Order\PromoCodeResource\Pages;

use App\Filament\Resources\Order\PromoCodeResource;
use App\Services\AdminAudit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPromoCode extends EditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data);
        $changed = array_values(array_diff(array_keys($record->getDirty()), ['updated_at']));
        $record->save();

        if ($changed) {
            AdminAudit::record('promo.updated', $record, ['champs' => $changed], label: $record->code);
        }

        return $record;
    }
}

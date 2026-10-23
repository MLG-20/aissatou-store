<?php

namespace App\Filament\Resources\Admin\AdminResource\Pages;

use App\Filament\Resources\Admin\AdminResource;
use App\Models\Admin;
use App\Services\AdminAccountService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    /** Le rôle n'est pas une colonne : on le présente au formulaire. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Admin $record */
        $record = $this->getRecord();
        $data['role'] = $record->getRoleNames()->first();
        $data['is_active'] = $record->is_active;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(AdminAccountService::class)->update($record, $data, filament()->auth()->user());
    }
}

<?php

namespace App\Filament\Resources\Content\ContactMessageResource\Pages;

use App\Filament\Resources\Content\ContactMessageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Réponse dans le client email de la boutique, avec l'objet
            // pré-rempli — pas d'envoi depuis le back-office en V1.
            Action::make('reply')
                ->label('Répondre par email')
                ->icon('heroicon-o-paper-airplane')
                ->url(fn ($record): string => 'mailto:'.$record->email.'?subject='.urlencode('Re: votre message à Aissatou Store')),
            Action::make('toggleRead')
                ->label(fn ($record): string => $record->is_read ? 'Marquer non lu' : 'Marquer comme lu')
                ->action(fn ($record): bool => $record->update(['is_read' => ! $record->is_read])),
            DeleteAction::make(),
        ];
    }
}

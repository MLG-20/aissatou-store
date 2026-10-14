<?php

namespace App\Filament\Resources\Order\OrderResource\Pages;

use App\Enums\Order\OrderStatus;
use App\Exceptions\OrderException;
use App\Filament\Resources\Order\OrderResource;
use App\Models\Order\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Commande')
                    ->schema([
                        TextEntry::make('number')->label('N° de commande'),
                        TextEntry::make('status')
                            ->label('Statut')
                            ->formatStateUsing(fn (OrderStatus $state): string => $state->label()),
                        TextEntry::make('created_at')->label('Passée le')->dateTime('d/m/Y à H:i'),
                        TextEntry::make('total')->label('Total')->money('XOF'),
                    ])
                    ->columns(2),

                Section::make('Client et livraison')
                    ->schema([
                        TextEntry::make('user.name')->label('Client'),
                        TextEntry::make('user.email')->label('Email'),
                        TextEntry::make('recipient_name')->label('Destinataire'),
                        TextEntry::make('phone')->label('Téléphone'),
                        TextEntry::make('address_line')->label('Adresse'),
                        TextEntry::make('city')->label('Ville'),
                        TextEntry::make('landmark')->label('Repère')->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Articles (prix figés à l\'achat)')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('product_name')->label('Produit'),
                                TextEntry::make('variant_label')->label('Déclinaison')->placeholder('—'),
                                TextEntry::make('sku')->label('Réf.'),
                                TextEntry::make('quantity')->label('Qté'),
                                TextEntry::make('unit_price')->label('Prix unit.')->money('XOF'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Paiement')
                    ->schema([
                        TextEntry::make('payment.provider')
                            ->label('Moyen')
                            ->formatStateUsing(fn (Order $record): string => $record->payment->providerLabel()),
                        TextEntry::make('payment.reference')->label('Référence')->placeholder('—'),
                        TextEntry::make('payment.amount')->label('Montant')->money('XOF'),
                    ])
                    ->columns(3),

                Section::make('Livraison')
                    ->schema([
                        TextEntry::make('shipping_zone')->label('Zone appliquée')->placeholder('—'),
                        TextEntry::make('shipping_total')->label('Frais')->money('XOF'),
                        TextEntry::make('delivery_assignee')->label('Livreur / mode')->placeholder('Non assignée'),
                        TextEntry::make('delivery_note')->label('Note')->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Historique des statuts')
                    ->schema([
                        RepeatableEntry::make('history')
                            ->label('')
                            ->schema([
                                TextEntry::make('created_at')->label('Date')->dateTime('d/m/Y H:i'),
                                TextEntry::make('from_status')->label('De')->placeholder('création'),
                                TextEntry::make('to_status')->label('Vers'),
                                TextEntry::make('author.name')->label('Par')->placeholder('système'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Seul l'admin authentifié change un statut (le panel Filament est
            // derrière le guard `admin`) : la transition est validée + tracée
            // par OrderService, avec l'admin en auteur.
            Action::make('changeStatus')
                ->label('Changer le statut')
                ->icon('heroicon-o-arrow-path')
                ->form([
                    Select::make('status')
                        ->label('Nouveau statut')
                        ->options(fn (Order $record): array => collect($record->status->allowedTransitions())
                            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->label()])
                            ->all())
                        ->required(),
                ])
                ->action(function (Order $record, array $data): void {
                    try {
                        app(OrderService::class)->changeStatus(
                            $record,
                            OrderStatus::from($data['status']),
                            filament()->auth()->id(),
                        );
                    } catch (OrderException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Statut mis à jour.')->send();
                }),

            Action::make('invoice')
                ->label('Facture PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (Order $record): string => route('admin.orders.invoice', $record))
                ->openUrlInNewTab(),

            // Assignation manuelle (Phase 4) : qui livre, avec quelle consigne.
            // Réservée à l'admin (guard du panel) ; visible par le client dans
            // son suivi une fois renseignée.
            Action::make('assignDelivery')
                ->label('Organiser la livraison')
                ->icon('heroicon-o-truck')
                ->form([
                    TextInput::make('delivery_assignee')
                        ->label('Livreur / mode')
                        ->helperText('Nom du livreur ou mode (« moto », « retrait boutique »…).')
                        ->maxLength(255),
                    Textarea::make('delivery_note')
                        ->label('Note')
                        ->helperText('Consigne visible par le client dans son suivi.')
                        ->rows(2)
                        ->maxLength(1000),
                ])
                ->action(function (Order $record, array $data): void {
                    $record->update([
                        'delivery_assignee' => $data['delivery_assignee'] ?: null,
                        'delivery_note' => $data['delivery_note'] ?: null,
                    ]);

                    Notification::make()->success()->title('Livraison organisée.')->send();
                }),
        ];
    }
}

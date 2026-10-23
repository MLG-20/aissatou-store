<?php

namespace App\Filament\Resources\Order\OrderResource\Pages;

use App\Enums\Admin\AdminPermission;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Exceptions\OrderException;
use App\Filament\Resources\Order\OrderResource;
use App\Models\Admin;
use App\Models\Order\Order;
use App\Models\Order\PaymentEvent;
use App\Services\AdminAudit;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use App\Support\KeyValueSummary;
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
                        TextEntry::make('discount_total')
                            ->label('Remise')
                            ->formatStateUsing(fn (Order $record): string => '-'.number_format((float) $record->discount_total, 0, ',', ' ').' FCFA (code '.$record->promo_code.')')
                            ->visible(fn (Order $record): bool => (float) $record->discount_total > 0),
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
                            ->label('Fournisseur')
                            ->formatStateUsing(fn (Order $record): string => $record->payment->providerLabel()),
                        TextEntry::make('payment.status')
                            ->label('Statut réel')
                            ->badge()
                            ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                            ->color(fn (PaymentStatus $state): string => match ($state) {
                                PaymentStatus::Confirmed => 'success',
                                PaymentStatus::Failed => 'danger',
                                PaymentStatus::Refunded => 'warning',
                                PaymentStatus::Processing => 'info',
                                PaymentStatus::Pending => 'gray',
                            }),
                        TextEntry::make('payment.amount')->label('Montant')->money('XOF'),
                        TextEntry::make('payment.method')->label('Moyen utilisé')->placeholder('—'),
                        // Référence de la tentative payée, à retrouver telle
                        // quelle dans le tableau de bord PayTech.
                        TextEntry::make('payment.reference')->label('Référence transaction')->placeholder('—')->copyable(),
                        TextEntry::make('payment.confirmed_at')->label('Confirmé le')->dateTime('d/m/Y à H:i')->placeholder('—'),
                        TextEntry::make('payment.expires_at')
                            ->label('Annulation auto si impayé')
                            ->dateTime('d/m/Y à H:i')
                            ->visible(fn (Order $record): bool => $record->payment->expires_at !== null
                                && $record->payment->status !== PaymentStatus::Confirmed),
                    ])
                    ->columns(3),

                // Journal financier immuable (phase 5) : de quoi trancher un
                // litige sans ambiguïté — qui a dit quoi, quand, depuis où.
                Section::make('Journal du paiement')
                    ->visible(fn (Order $record): bool => self::canSeePayments()
                        && ($record->payment?->events()->exists() ?? false))
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('payment.events')
                            ->label('')
                            ->schema([
                                TextEntry::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s'),
                                TextEntry::make('type')
                                    ->label('Événement')
                                    ->badge()
                                    ->formatStateUsing(fn (PaymentEventType $state): string => $state->label())
                                    ->color(fn (PaymentEventType $state): string => match ($state) {
                                        PaymentEventType::Confirmed => 'success',
                                        PaymentEventType::Failed, PaymentEventType::InitiationFailed, PaymentEventType::Expired => 'warning',
                                        PaymentEventType::SuspiciousNotification => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('ip')->label('IP')->placeholder('serveur'),
                                TextEntry::make('details')
                                    ->label('Détails')
                                    ->getStateUsing(fn (PaymentEvent $record): ?string => KeyValueSummary::of($record->payload))
                                    ->placeholder('—'),
                            ])
                            ->columns(4),
                    ]),

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

    /** Journal + vérification des paiements : admin complet (décision 6A). */
    private static function canSeePayments(): bool
    {
        $admin = filament()->auth()->user();

        return $admin instanceof Admin && $admin->hasAdminPermission(AdminPermission::Payments);
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
                    $from = $record->status;

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

                    AdminAudit::record('order.status_changed', $record, ['de' => $from->value, 'vers' => $data['status']]);

                    Notification::make()->success()->title('Statut mis à jour.')->send();
                }),

            // Réconciliation manuelle (litige, IPN perdu) : interroge le
            // fournisseur — l'admin ne peut pas « déclarer » un paiement payé,
            // seul le fournisseur fait foi.
            Action::make('verifyPayment')
                ->label('Vérifier le paiement')
                ->icon('heroicon-o-shield-check')
                ->visible(fn (Order $record): bool => self::canSeePayments() && (bool) $record->payment?->isOnline())
                ->action(function (Order $record): void {
                    // Revérifié à l'exécution : une action masquée peut quand
                    // même être appelée en forgeant la requête Livewire.
                    abort_unless(self::canSeePayments(), 403);

                    $before = $record->payment->status;
                    $after = app(PaymentService::class)->reconcile($record->payment, request()->ip());
                    AdminAudit::record('payment.verified', $record, ['avant' => $before->value, 'apres' => $after->value]);

                    Notification::make()
                        ->title($after === $before
                            ? 'Statut inchangé : '.$after->label()
                            : 'Statut mis à jour : '.$before->label().' → '.$after->label())
                        ->body($after === PaymentStatus::Processing
                            ? 'Le fournisseur indique un paiement non finalisé (ou est injoignable).'
                            : null)
                        ->color($after === PaymentStatus::Confirmed ? 'success' : 'warning')
                        ->send();
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
                    AdminAudit::record('order.delivery_assigned', $record, ['livreur' => $record->delivery_assignee]);

                    Notification::make()->success()->title('Livraison organisée.')->send();
                }),
        ];
    }
}

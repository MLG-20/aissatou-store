<?php

namespace App\Filament\Resources\Order;

use App\Enums\Admin\AdminPermission;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Filament\Clusters\Payments;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Order\PaymentResource\Pages;
use App\Models\Order\Payment;
use App\Models\Order\PaymentEvent;
use App\Services\Payment\PaymentManager;
use App\Support\PaymentEventDetails;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Paiements en ligne, une ligne par commande : la fiche raconte tout le
 * déroulement à partir du journal financier immuable (phase 5). Lecture
 * seule — seul le fournisseur fait foi.
 */
class PaymentResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Payments;
    }

    protected static ?string $model = Payment::class;

    protected static ?string $cluster = Payments::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $slug = 'suivi';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Paiements';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'paiement';
    }

    public static function getPluralModelLabel(): string
    {
        return 'paiements';
    }

    /** Le paiement à la livraison n'a pas de déroulement à suivre ici. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('provider', app(PaymentManager::class)->onlineKeys())
            ->with('order.user');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('order.number')
                    ->label('Commande')
                    ->searchable(),
                TextColumn::make('client')
                    ->label('Client')
                    ->getStateUsing(fn (Payment $record): ?string => self::clientName($record))
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XOF'),
                TextColumn::make('method')
                    ->label('Moyen de paiement')
                    ->placeholder('—'),
                TextColumn::make('outcome')
                    ->label('Statut')
                    ->badge()
                    ->getStateUsing(fn (Payment $record): string => self::outcome($record))
                    ->color(fn (Payment $record): string => self::outcomeColor($record)),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(PaymentStatus::options()),
            ])
            ->actions([
                ViewAction::make()->label('Voir la fiche'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Résumé')
                    ->schema([
                        TextEntry::make('order.number')
                            ->label('Commande')
                            ->url(fn (Payment $record): string => OrderResource::getUrl('view', ['record' => $record->order]))
                            ->color('primary'),
                        TextEntry::make('client')
                            ->label('Client')
                            ->getStateUsing(fn (Payment $record): ?string => self::clientName($record))
                            ->placeholder('—'),
                        TextEntry::make('amount')
                            ->label('Montant')
                            ->money('XOF'),
                        TextEntry::make('outcome')
                            ->label('Statut')
                            ->badge()
                            ->getStateUsing(fn (Payment $record): string => self::outcome($record))
                            ->color(fn (Payment $record): string => self::outcomeColor($record)),
                        TextEntry::make('method')
                            ->label('Moyen de paiement')
                            ->placeholder('—'),
                        TextEntry::make('confirmed_at')
                            ->label('Payé le')
                            ->dateTime('d/m/Y à H:i')
                            ->placeholder('—'),
                        // Référence de la tentative payée, à retrouver telle
                        // quelle dans le tableau de bord PayTech.
                        TextEntry::make('reference')
                            ->label('Référence PayTech')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('expires_at')
                            ->label('Annulation auto si impayé')
                            ->dateTime('d/m/Y à H:i')
                            ->visible(fn (Payment $record): bool => $record->expires_at !== null
                                && $record->status !== PaymentStatus::Confirmed),
                    ])
                    ->columns(4),

                Section::make('Déroulement')
                    ->description('Toutes les étapes du paiement, de la plus ancienne à la plus récente.')
                    ->schema([
                        RepeatableEntry::make('events')
                            ->label('')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Le')
                                    ->dateTime('d/m/Y à H:i:s'),
                                TextEntry::make('type')
                                    ->label('Ce qui s\'est passé')
                                    ->badge()
                                    ->formatStateUsing(fn (PaymentEventType $state): string => $state->label())
                                    ->color(fn (PaymentEventType $state): string => $state->color()),
                                TextEntry::make('explanation')
                                    ->label('En clair')
                                    ->getStateUsing(fn (PaymentEvent $record): string => $record->type->description())
                                    ->columnSpan(2),
                                // Payload déjà expurgé à l'écriture (ni
                                // empreintes de clés, ni numéro complet du
                                // payeur) : replié, utile en cas de litige.
                                Section::make('Détails')
                                    ->collapsed()
                                    ->compact()
                                    ->schema([
                                        KeyValueEntry::make('details')
                                            ->label('')
                                            ->keyLabel('Information')
                                            ->valueLabel('Valeur')
                                            ->getStateUsing(fn (PaymentEvent $record): array => PaymentEventDetails::of($record->payload))
                                            ->visible(fn (PaymentEvent $record): bool => filled($record->payload)),
                                        TextEntry::make('ip')
                                            ->label('Adresse IP d\'origine')
                                            ->placeholder('boutique (action déclenchée par le site lui-même)'),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    /** Compte client, sinon destinataire saisi au checkout. */
    private static function clientName(Payment $record): ?string
    {
        return $record->order?->user?->name ?? $record->order?->recipient_name;
    }

    /** Statut lisible : un échec suivi de l'annulation se dit en clair. */
    private static function outcome(Payment $record): string
    {
        return match (true) {
            $record->status === PaymentStatus::Confirmed => 'Payé',
            $record->status === PaymentStatus::Failed
                && $record->order?->status === OrderStatus::Cancelled => 'Impayé, commande annulée',
            default => $record->status->label(),
        };
    }

    private static function outcomeColor(Payment $record): string
    {
        return match ($record->status) {
            PaymentStatus::Confirmed => 'success',
            PaymentStatus::Processing => 'info',
            PaymentStatus::Failed => 'warning',
            PaymentStatus::Refunded => 'danger',
            PaymentStatus::Pending => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}

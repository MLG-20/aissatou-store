<?php

namespace App\Filament\Resources\Order;

use App\Enums\Admin\AdminPermission;
use App\Enums\Order\PaymentEventType;
use App\Filament\Clusters\Payments;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Order\PaymentEventResource\Pages;
use App\Models\Order\PaymentEvent;
use App\Support\PaymentEventDetails;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Onglet « Messages suspects » des paiements : messages se faisant passer
 * pour PayTech, rejetés (signature, commande ou montant incorrect), y
 * compris ceux qui ne visent aucune commande. Lecture seule — le journal
 * financier est immuable, rien ne se crée, ne se modifie ni ne se supprime.
 */
class PaymentEventResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Payments;
    }

    protected static ?string $model = PaymentEvent::class;

    protected static ?string $cluster = Payments::class;

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $slug = 'messages-suspects';

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Messages suspects';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'message suspect';
    }

    public static function getPluralModelLabel(): string
    {
        return 'messages suspects';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', PaymentEventType::SuspiciousNotification);
    }

    public static function getNavigationBadge(): ?string
    {
        return Payments::getNavigationBadge();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return Payments::getNavigationBadgeTooltip();
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
                TextColumn::make('reason')
                    ->label('Motif du rejet')
                    ->getStateUsing(fn (PaymentEvent $record): ?string => $record->payload['motif'] ?? null)
                    ->placeholder('—'),
                TextColumn::make('payment.order.number')
                    ->label('Commande visée')
                    ->placeholder('aucune')
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                ViewAction::make()->label('Voir la fiche'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Ce qui s\'est passé')
                    ->schema([
                        TextEntry::make('type')
                            ->label('Événement')
                            ->badge()
                            ->formatStateUsing(fn (PaymentEventType $state): string => $state->label())
                            ->color(fn (PaymentEventType $state): string => $state->color()),
                        TextEntry::make('created_at')
                            ->label('Le')
                            ->dateTime('d/m/Y à H:i:s'),
                        TextEntry::make('explanation')
                            ->label('En clair')
                            ->getStateUsing(fn (PaymentEvent $record): string => $record->type->description())
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Commande visée')
                    ->schema([
                        TextEntry::make('payment.order.number')
                            ->label('Commande')
                            ->placeholder('Aucune commande correspondante')
                            ->url(fn (PaymentEvent $record): ?string => $record->payment?->order
                                ? OrderResource::getUrl('view', ['record' => $record->payment->order])
                                : null)
                            ->color('primary'),
                        TextEntry::make('client')
                            ->label('Client')
                            ->getStateUsing(fn (PaymentEvent $record): ?string => self::clientName($record))
                            ->placeholder('—'),
                        TextEntry::make('payment.amount')
                            ->label('Montant de la commande')
                            ->money('XOF')
                            ->placeholder('—'),
                        TextEntry::make('payment.method')
                            ->label('Moyen de paiement')
                            ->placeholder('—'),
                    ])
                    ->columns(4),

                Section::make('Informations transmises')
                    ->visible(fn (PaymentEvent $record): bool => filled($record->payload))
                    ->schema([
                        // Payload déjà expurgé à l'écriture (ni empreintes de
                        // clés, ni numéro complet du payeur).
                        KeyValueEntry::make('details')
                            ->label('')
                            ->keyLabel('Information')
                            ->valueLabel('Valeur')
                            ->getStateUsing(fn (PaymentEvent $record): array => PaymentEventDetails::of($record->payload)),
                    ]),

                Section::make('Informations techniques')
                    ->description('Utiles en cas de litige ou pour le support technique.')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('id')->label('N° de l\'événement'),
                        TextEntry::make('provider')->label('Fournisseur'),
                        TextEntry::make('ip')
                            ->label('Adresse IP d\'origine')
                            ->placeholder('boutique')
                            ->helperText('« boutique » : action déclenchée par le site lui-même.'),
                    ])
                    ->columns(3),
            ]);
    }

    /** Compte client, sinon destinataire saisi au checkout. */
    private static function clientName(PaymentEvent $record): ?string
    {
        $order = $record->payment?->order;

        return $order?->user?->name ?? $order?->recipient_name;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentEvents::route('/'),
            'view' => Pages\ViewPaymentEvent::route('/{record}'),
        ];
    }
}

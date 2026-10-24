<?php

namespace App\Filament\Resources\Order;

use App\Enums\Admin\AdminPermission;
use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Order\PaymentEventResource\Pages;
use App\Models\Order\PaymentEvent;
use App\Support\KeyValueSummary;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal financier (phase 5) : toutes les tentatives, notifications et
 * vérifications, y compris les notifications suspectes qui ne se rattachent
 * à aucune commande. Lecture seule — le journal est immuable, rien ne se
 * crée, ne se modifie ni ne se supprime ici.
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

    protected static ?string $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Journal des paiements';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'événement de paiement';
    }

    public static function getPluralModelLabel(): string
    {
        return 'journal des paiements';
    }

    /** Alerte : notifications suspectes des 7 derniers jours. */
    public static function getNavigationBadge(): ?string
    {
        $suspicious = PaymentEvent::query()
            ->where('type', PaymentEventType::SuspiciousNotification)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return $suspicious > 0 ? (string) $suspicious : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Événement')
                    ->badge()
                    ->formatStateUsing(fn (PaymentEventType $state): string => $state->label())
                    ->color(fn (PaymentEventType $state): string => match ($state) {
                        PaymentEventType::Confirmed => 'success',
                        PaymentEventType::Failed, PaymentEventType::InitiationFailed, PaymentEventType::Expired => 'warning',
                        PaymentEventType::SuspiciousNotification => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('payment.order.number')
                    ->label('Commande')
                    ->placeholder('inconnue')
                    ->searchable()
                    ->url(fn (PaymentEvent $record): ?string => $record->payment?->order
                        ? OrderResource::getUrl('view', ['record' => $record->payment->order])
                        : null),
                TextColumn::make('provider')->label('Fournisseur'),
                TextColumn::make('status')
                    ->label('Statut paiement')
                    ->formatStateUsing(fn (?string $state): string => PaymentStatus::tryFrom((string) $state)?->label() ?? '—'),
                TextColumn::make('ip')->label('IP')->placeholder('serveur'),
                TextColumn::make('details')
                    ->label('Détails')
                    // Payload déjà expurgé à l'écriture (ni empreintes de clés,
                    // ni numéro complet du payeur).
                    ->getStateUsing(fn (PaymentEvent $record): ?string => KeyValueSummary::of($record->payload))
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Événement')
                    ->options(collect(PaymentEventType::cases())
                        ->mapWithKeys(fn (PaymentEventType $type) => [$type->value => $type->label()])
                        ->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentEvents::route('/'),
        ];
    }
}

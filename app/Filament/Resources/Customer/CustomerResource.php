<?php

namespace App\Filament\Resources\Customer;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Customer\CustomerResource\Pages;
use App\Filament\Resources\Customer\CustomerResource\RelationManagers\OrdersRelationManager;
use App\Models\User;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Clients (Phase 6E) : segmentation et historique d'achats. Données
 * personnelles (habitudes d'achat) : admin complet uniquement, lecture
 * seule — le back-office ne crée, ne modifie ni ne supprime de compte
 * client. Chaque consultation de fiche est tracée au journal d'activité.
 */
class CustomerResource extends Resource
{
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Customers;
    }

    protected static ?string $model = User::class;

    protected static ?string $slug = 'clients';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Clients';

    protected static ?string $navigationLabel = 'Clients';

    public static function getModelLabel(): string
    {
        return 'client';
    }

    public static function getPluralModelLabel(): string
    {
        return 'clients';
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withPurchaseStats();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('phone')->label('Téléphone')->placeholder('—')->toggleable(),
                TextColumn::make('paid_orders_count')->label('Commandes payées')->sortable(),
                TextColumn::make('total_spent')
                    ->label('Total dépensé')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', ' ').' FCFA')
                    ->sortable(),
                TextColumn::make('last_paid_at')
                    ->label('Dernier achat')
                    ->dateTime('d/m/Y')
                    ->placeholder('jamais')
                    ->sortable(),
                TextColumn::make('created_at')->label('Inscrite le')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_paid_at', 'desc')
            ->filters([
                Filter::make('regular')
                    ->label('Réguliers (≥ 2 commandes payées en 6 mois)')
                    ->query(fn (Builder $query): Builder => $query->regularCustomers()),
                Filter::make('abandoned')
                    ->label('Panier abandonné')
                    ->query(fn (Builder $query): Builder => $query->withAbandonedCart()),
                Filter::make('inactive')
                    ->label('Inactifs (aucun achat payé depuis 90 jours)')
                    ->query(fn (Builder $query): Builder => $query->inactiveCustomers()),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Cliente')
                ->schema([
                    TextEntry::make('name')->label('Nom'),
                    TextEntry::make('email')->label('Email')->copyable(),
                    TextEntry::make('phone')->label('Téléphone')->placeholder('—'),
                    TextEntry::make('created_at')->label('Inscrite le')->date('d/m/Y'),
                    TextEntry::make('segments')
                        ->label('Segments')
                        ->badge()
                        ->getStateUsing(fn (User $record): array => $record->segments() ?: ['Aucun'])
                        ->color(fn (string $state): string => match ($state) {
                            'Régulière' => 'success',
                            'Panier abandonné' => 'warning',
                            'Inactive' => 'gray',
                            default => 'gray',
                        }),
                ])
                ->columns(3),

            Section::make('Achats')
                ->schema([
                    TextEntry::make('paid_orders_count')->label('Commandes payées'),
                    TextEntry::make('total_spent')
                        ->label('Total dépensé')
                        ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', ' ').' FCFA'),
                    TextEntry::make('average_basket')
                        ->label('Panier moyen')
                        ->getStateUsing(fn (User $record): string => $record->paid_orders_count > 0
                            ? number_format($record->total_spent / $record->paid_orders_count, 0, ',', ' ').' FCFA'
                            : '—'),
                    TextEntry::make('last_paid_at')->label('Dernier achat payé')->dateTime('d/m/Y à H:i')->placeholder('jamais'),
                ])
                ->columns(4),

            Section::make('Panier en cours')
                ->visible(fn (User $record): bool => $record->cart?->items()->exists() ?? false)
                ->description(fn (User $record): ?string => $record->cart?->abandoned_at
                    ? 'Abandonné : sans modification depuis le '.$record->cart->updated_at->format('d/m/Y à H:i').'.'
                    : null)
                ->schema([
                    RepeatableEntry::make('cart.items')
                        ->label('')
                        ->schema([
                            TextEntry::make('product.name')->label('Article'),
                            TextEntry::make('quantity')->label('Qté'),
                            TextEntry::make('line')
                                ->label('Montant (prix actuel)')
                                ->getStateUsing(fn ($record): string => number_format($record->lineTotal(), 0, ',', ' ').' FCFA'),
                        ])
                        ->columns(3),
                ]),

            Section::make('Adresses')
                ->collapsible()
                ->collapsed()
                ->schema([
                    RepeatableEntry::make('addresses')
                        ->label('')
                        ->schema([
                            TextEntry::make('recipient_name')->label('Destinataire'),
                            TextEntry::make('phone')->label('Téléphone'),
                            TextEntry::make('address_line')->label('Adresse')
                                ->formatStateUsing(fn ($record): string => $record->address_line.', '.$record->city),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [OrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}

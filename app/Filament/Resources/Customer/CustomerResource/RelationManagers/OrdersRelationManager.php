<?php

namespace App\Filament\Resources\Customer\CustomerResource\RelationManagers;

use App\Enums\Admin\AdminPermission;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\Order\OrderResource;
use App\Models\Admin;
use App\Models\Order\Order;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Historique complet des achats d'une cliente (Phase 6E) : toutes ses
 * commandes, payées ou non, avec lien vers la fiche commande.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Historique des commandes';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof Admin && $admin->hasAdminPermission(AdminPermission::Customers);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')->label('N°'),
                TextColumn::make('created_at')->label('Passée le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label()),
                TextColumn::make('payment_status')
                    ->label('Paiement')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state === PaymentStatus::Confirmed ? 'success' : 'gray'),
                TextColumn::make('promo_code')->label('Code promo')->placeholder('—')->toggleable(),
                TextColumn::make('total')->label('Total')->money('XOF'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('open')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\Order\OrderResource\Pages;
use App\Models\Order\Order;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Les commandes naissent dans le tunnel client, jamais ici : pas de
 * création manuelle, lecture + changement de statut + facture uniquement.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Commandes';

    public static function getModelLabel(): string
    {
        return 'commande';
    }

    public static function getPluralModelLabel(): string
    {
        return 'commandes';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('N°')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->label())
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::New => 'info',
                        OrderStatus::Preparing => 'warning',
                        OrderStatus::Shipped => 'gray',
                        OrderStatus::Delivered => 'success',
                        OrderStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('payment.status')
                    ->label('Paiement')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::Confirmed => 'success',
                        PaymentStatus::Failed => 'danger',
                        PaymentStatus::Refunded => 'warning',
                        PaymentStatus::Pending => 'gray',
                    }),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('XOF')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Passée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(OrderStatus::options()),
                SelectFilter::make('payment_status')
                    ->label('Paiement')
                    ->options(PaymentStatus::options()),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}

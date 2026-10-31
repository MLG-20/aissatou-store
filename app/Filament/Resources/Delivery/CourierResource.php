<?php

namespace App\Filament\Resources\Delivery;

use App\Enums\Admin\AdminPermission;
use App\Enums\Delivery\CourierType;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Delivery\CourierResource\Pages;
use App\Models\Delivery\Courier;
use App\Rules\SenegalPhone;
use App\Services\AdminAudit;
use App\Services\Delivery\CourierService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Livreurs : internes ou services extérieurs. Un livreur qui a déjà livré
 * se désactive au lieu de se supprimer, pour garder l'historique.
 */
class CourierResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Delivery;
    }

    protected static ?string $model = Courier::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Livraison';

    protected static ?string $navigationLabel = 'Livreurs';

    public static function getModelLabel(): string
    {
        return 'livreur';
    }

    public static function getPluralModelLabel(): string
    {
        return 'livreurs';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom')
                    ->helperText('Prénom du livreur, ou nom du service (« Yango »). Affiché à la cliente dans son suivi.')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Numéro WhatsApp')
                    ->helperText('Il y reçoit ses livraisons.')
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    ->rule(new SenegalPhone),
                Select::make('type')
                    ->label('Type')
                    ->options(CourierType::options())
                    ->default(CourierType::Internal->value)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->helperText('Un livreur désactivé n\'est plus proposé et ses liens ne marchent plus, sans perdre son historique.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('phone')->label('WhatsApp'),
                TextColumn::make('type')->label('Type')
                    ->formatStateUsing(fn (CourierType $state): string => $state->label())
                    ->badge(),
                TextColumn::make('orders_in_progress_count')
                    ->label('En cours')
                    ->counts('ordersInProgress'),
                TextColumn::make('tour_token_issued_at')
                    ->label('Lien « Ma tournée »')
                    ->formatStateUsing(fn ($state): string => 'Envoyé le '.$state->format('d/m/Y'))
                    ->placeholder('Aucun'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')->label('Actif'),
            ])
            ->actions([
                // Lien personnel : un nouvel envoi désactive l'ancien (téléphone
                // perdu, lien transféré). Le lien n'est montré qu'ici, une fois.
                Action::make('sendTourLink')
                    ->label(fn (Courier $record): string => $record->tour_token_hash ? 'Renvoyer « Ma tournée »' : 'Envoyer « Ma tournée »')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Courier $record): bool => $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer sa page « Ma tournée »')
                    ->modalDescription(fn (Courier $record): string => 'Un lien personnel est créé pour '.$record->name.', avec toutes ses livraisons en cours.'
                        .($record->tour_token_hash ? ' L\'ancien lien cessera aussitôt de marcher.' : ''))
                    ->modalSubmitActionLabel('Créer le lien')
                    ->action(function (Courier $record): void {
                        $couriers = app(CourierService::class);
                        $link = $couriers->issueTourLink($record);
                        AdminAudit::record('courier.tour_link_sent', $record);

                        Notification::make()
                            ->success()
                            ->title('Lien « Ma tournée » créé pour '.$record->name)
                            ->body('Envoyez-le maintenant : il ne sera plus affiché ensuite.')
                            ->actions([
                                NotificationAction::make('whatsapp')
                                    ->label('Ouvrir WhatsApp')
                                    ->url($couriers->tourWhatsAppUrl($record, $link), shouldOpenInNewTab: true)
                                    ->button(),
                            ])
                            ->persistent()
                            ->send();
                    }),
                Action::make('revokeTourLink')
                    ->label('Révoquer « Ma tournée »')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Courier $record): bool => $record->tour_token_hash !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Son lien « Ma tournée » cessera aussitôt de marcher. Les liens envoyés commande par commande restent valables.')
                    ->action(function (Courier $record): void {
                        app(CourierService::class)->revokeTourLink($record);
                        AdminAudit::record('courier.tour_link_revoked', $record);

                        Notification::make()->success()->title('Lien révoqué.')->send();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Courier $record): bool => $record->orders()->doesntExist()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCouriers::route('/'),
            'create' => Pages\CreateCourier::route('/create'),
            'edit' => Pages\EditCourier::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Order;

use App\Enums\Admin\AdminPermission;
use App\Enums\Order\PromoCodeType;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Order\PromoCodeResource\Pages;
use App\Models\Order\PromoCode;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Codes promo (Phase 6C), admin complet uniquement. Les règles
 * d'application (revalidation finale, compteur atomique) sont dans
 * PromoService ; ici, uniquement la saisie. Un code déjà utilisé ne se
 * supprime pas (il reste lié aux commandes) : on le désactive.
 */
class PromoCodeResource extends Resource
{
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Promotions;
    }

    protected static ?string $model = PromoCode::class;

    protected static ?string $slug = 'codes-promo';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Codes promo';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'code promo';
    }

    public static function getPluralModelLabel(): string
    {
        return 'codes promo';
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof PromoCode
            && $record->redemptions()->doesntExist()
            && parent::canDelete($record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Code')
                ->schema([
                    TextInput::make('code')
                        ->label('Code')
                        ->helperText('Lettres, chiffres, tiret ou souligné. Insensible à la casse (enregistré en majuscules).')
                        ->required()
                        ->maxLength(40)
                        ->regex('/^[A-Za-z0-9_-]+$/')
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (?string $state): string => PromoCode::normalize($state)),
                    TextInput::make('description')->label('Note interne')->maxLength(255),
                    Toggle::make('is_active')->label('Actif')->default(true),
                ])
                ->columns(3),

            Section::make('Réduction')
                ->description('Sur les articles uniquement, jamais sur la livraison.')
                ->schema([
                    Select::make('type')
                        ->label('Type')
                        ->options(PromoCodeType::options())
                        ->required()
                        ->live(),
                    TextInput::make('value')
                        ->label(fn (Get $get): string => $get('type') === PromoCodeType::Percent->value ? 'Pourcentage (%)' : 'Montant (FCFA)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(fn (Get $get): ?int => $get('type') === PromoCodeType::Percent->value ? 100 : null),
                ])
                ->columns(2),

            Section::make('Conditions')
                ->schema([
                    DateTimePicker::make('starts_at')->label('Début de validité')->seconds(false),
                    DateTimePicker::make('ends_at')->label('Fin de validité')->seconds(false)->after('starts_at'),
                    TextInput::make('min_subtotal')
                        ->label('Minimum d\'achat (FCFA, hors livraison)')
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('max_uses')
                        ->label('Nombre maximum d\'utilisations')
                        ->helperText('Vide = illimité. Une commande annulée rend son utilisation.')
                        ->numeric()
                        ->integer()
                        ->minValue(1),
                    Toggle::make('once_per_customer')->label('Une seule utilisation par client'),
                ])
                ->columns(2),

            Section::make('Ciblage (optionnel)')
                ->description('Sans ciblage, le code vaut pour tout le panier. Avec, seuls les articles ciblés sont réduits (une catégorie inclut ses sous-catégories).')
                ->schema([
                    Select::make('products')
                        ->label('Produits')
                        ->relationship('products', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload(),
                    Select::make('categories')
                        ->label('Catégories')
                        ->relationship('categories', 'name')
                        ->multiple()
                        ->preload(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->copyable()->weight('bold'),
                TextColumn::make('value')
                    ->label('Réduction')
                    ->formatStateUsing(fn (PromoCode $record): string => $record->valueLabel()),
                TextColumn::make('validity')
                    ->label('Validité')
                    ->getStateUsing(fn (PromoCode $record): string => ($record->starts_at?->format('d/m/Y') ?? '…').' → '.($record->ends_at?->format('d/m/Y') ?? '…')),
                TextColumn::make('uses_count')
                    ->label('Utilisations')
                    ->formatStateUsing(fn (PromoCode $record): string => $record->uses_count.($record->max_uses ? ' / '.$record->max_uses : '')),
                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->getStateUsing(fn (PromoCode $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        'Actif' => 'success',
                        'Programmé' => 'info',
                        'Désactivé' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}

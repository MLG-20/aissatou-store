<?php

namespace App\Filament\Resources\Delivery;

use App\Filament\Resources\Delivery\DeliveryZoneResource\Pages;
use App\Models\Delivery\DeliveryZone;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Livraison';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom de la zone')
                    ->helperText('Ex. « Dakar », « Pikine », « Autres zones ».<br>Affiché au client dans le tunnel de commande.')
                    ->required()
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Ville couverte')
                    ->helperText("La ville de l'adresse client qui déclenche cette zone (insensible à la casse et aux accents). <strong>Laissez vide pour la zone par défaut</strong>, appliquée quand aucune ville ne correspond.")
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('fee')
                    ->label('Frais (FCFA)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(0),
                TextInput::make('delay_label')
                    ->label('Délai estimé')
                    ->helperText('Texte libre, ex. « 24-48h », « 2-3 jours ». Affiché au client.')
                    ->maxLength(255),
                TextInput::make('position')
                    ->label('Ordre')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Une zone désactivée n\'est plus proposée, sans perdre son historique de paramétrage.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Zone')->searchable(),
                TextColumn::make('city')->label('Ville')->placeholder('Toutes (défaut)'),
                TextColumn::make('fee')->label('Frais')->money('XOF')->sortable(),
                TextColumn::make('delay_label')->label('Délai')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('position')
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryZones::route('/'),
            'create' => Pages\CreateDeliveryZone::route('/create'),
            'edit' => Pages\EditDeliveryZone::route('/{record}/edit'),
        ];
    }
}

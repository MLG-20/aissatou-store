<?php

namespace App\Filament\Resources\Catalogue;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Catalogue\MaterialResource\Pages;
use App\Models\Catalogue\Material;
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

/**
 * Valeurs du filtre « Matière » (Phase 2.2A) : liste fermée administrable —
 * c'est ce qui évite que « Soie » et « soie » créent deux filtres distincts.
 * Le slug est généré depuis le nom par le modèle.
 */
class MaterialResource extends Resource
{
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Catalogue;
    }

    protected static ?string $model = Material::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Matières';

    public static function getModelLabel(): string
    {
        return 'matière';
    }

    public static function getPluralModelLabel(): string
    {
        return 'matières';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom')
                    ->helperText('Ex. « Soie », « Modal », « Coton », « Laine & cachemire » (libellés de la maquette p.12).')
                    ->required()
                    ->maxLength(255),
                TextInput::make('position')
                    ->label('Ordre')
                    ->helperText("Ordre d'affichage dans le filtre côté boutique.")
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText("Une matière désactivée disparaît du filtre et n'est plus utilisable — sans casser les produits déjà liés.")
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('products_count')->label('Produits')->counts('products')->sortable(),
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
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}

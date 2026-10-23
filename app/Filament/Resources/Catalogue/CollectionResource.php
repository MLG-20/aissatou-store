<?php

namespace App\Filament\Resources\Catalogue;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Catalogue\CollectionResource\Pages;
use App\Models\Catalogue\Collection;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Le récit éditorial (Phase 2.2B) : la cliente crée la collection, lui donne
 * une image et y rattache des produits — la page publique suit aussitôt.
 */
class CollectionResource extends Resource
{
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Catalogue;
    }

    protected static ?string $model = Collection::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Collections';

    public static function getModelLabel(): string
    {
        return 'collection';
    }

    public static function getPluralModelLabel(): string
    {
        return 'collections';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom')
                    ->helperText('Ex. « Atelier Nocturne », « Essentiels », « Saison », « L\'art d\'offrir » (maquette p.12).')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Description')
                    ->helperText('Le récit de la collection, affiché en tête de sa page.')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Image')
                    ->helperText('Bannière de la page collection. 15 Mo maximum.')
                    ->image()
                    ->disk('public')
                    ->directory('collections')
                    ->visibility('public')
                    ->maxSize(15360)
                    ->columnSpanFull(),
                Select::make('products')
                    ->label('Produits de la collection')
                    ->helperText("Un produit peut appartenir à plusieurs collections — c'est ce qui distingue une collection d'une catégorie.")
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                TextInput::make('position')
                    ->label('Ordre')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Visible sur le site')
                    ->helperText('Décochez pour retirer la collection sans la supprimer — sa page renvoie alors 404.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->limit(1),
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('products_count')->label('Produits')->counts('products')->sortable(),
                IconColumn::make('is_active')->label('Visible')->boolean(),
            ])
            ->defaultSort('position')
            ->filters([
                TernaryFilter::make('is_active')->label('Visible'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'edit' => Pages\EditCollection::route('/{record}/edit'),
        ];
    }
}

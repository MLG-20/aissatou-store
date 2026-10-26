<?php

namespace App\Filament\Resources\Catalogue;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Forms\FocalPointFields;
use App\Filament\Resources\Catalogue\CategoryResource\Pages;
use App\Models\Catalogue\Category;
use App\Support\ImageSlot;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Catalogue;
    }

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Catégories';

    public static function getModelLabel(): string
    {
        return 'catégorie';
    }

    public static function getPluralModelLabel(): string
    {
        return 'catégories';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->label('Adresse de la page (lien)')
                    ->helperText("Rempli automatiquement à partir du nom : c'est ce qui apparaît dans l'adresse du site (ex. « Voiles & Hijabs » → voiles-hijabs). Les accents et espaces sont retirés pour former une adresse valide — en général, ne pas y toucher.")
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Select::make('parent_id')
                    ->label('Catégorie parente')
                    ->helperText('Laissez vide pour une catégorie principale. Choisissez une catégorie pour en faire une sous-catégorie.')
                    ->relationship('parent', 'name', fn ($query, $record) => $record
                        ? $query->whereKeyNot($record->id)
                        : $query)
                    ->searchable()
                    ->preload(),

                Textarea::make('description')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),

                // Champ « Changer l'image » : en édition il démarre vide (l'image
                // actuelle est montrée dans l'aperçu du cadrage). Vide = conservée ;
                // fichier déposé = remplacée (variantes régénérées par l'observer).
                ImageSlot::Category->configure(FileUpload::make('image_path'))
                    ->label(fn (string $operation): string => $operation === 'create'
                        ? 'Image de la catégorie'
                        : "Changer l'image")
                    ->disk('public')
                    ->directory('categories')
                    ->visibility('public')
                    ->formatStateUsing(fn (string $operation, $state) => $operation === 'edit' ? null : $state)
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText(fn (string $operation): HtmlString => ImageSlot::Category->helperText(
                        $operation === 'create' ? 'Facultatif.' : 'Laissez vide pour conserver l\'image ci-dessus.'
                    ))
                    ->columnSpanFull(),

                // La même photo sert de tuile portrait sur l'accueil et de
                // bandeau large en tête de la page catégorie.
                ...FocalPointFields::make([
                    'Tuile de l\'accueil' => '4 / 5',
                    'Grande tuile' => '3 / 2',
                    'Bandeau de la page' => '18 / 5',
                ]),

                TextInput::make('position')
                    ->label("Ordre d'affichage")
                    ->helperText('Un plus petit nombre apparaît en premier (0 = tout en haut).')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')
                    ->label('Visible sur le site')
                    ->helperText('Décochez pour masquer la catégorie sans la supprimer.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')->label('Image')->disk('public'),
                TextColumn::make('name')->label('Nom')->searchable(),
                ImageSlot::Category->qualityColumn(fn (Category $category) => $category->image_path),
                TextColumn::make('parent.name')->label('Parent')->placeholder('—'),
                TextColumn::make('position')->label('Position')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('position');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}

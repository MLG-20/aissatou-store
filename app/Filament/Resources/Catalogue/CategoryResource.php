<?php

namespace App\Filament\Resources\Catalogue;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Catalogue\CategoryResource\Pages;
use App\Models\Catalogue\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\Facades\Storage;
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

                // Aperçu de l'image actuelle (édition uniquement).
                Placeholder::make('current_image_preview')
                    ->label('Image actuelle')
                    ->content(function (?Category $record): HtmlString {
                        if (! $record?->image_path) {
                            return new HtmlString(
                                '<span style="color:#6b7280;">Aucune image — la catégorie affiche le visuel « signature » par défaut.</span>'
                            );
                        }

                        $url = Storage::disk('public')->url($record->image_path);

                        return new HtmlString(
                            '<img src="'.e($url).'" alt="Image actuelle" '
                            .'style="max-width:100%;max-height:220px;border-radius:0.75rem;'
                            .'box-shadow:0 1px 3px rgba(0,0,0,.15);object-fit:cover;">'
                        );
                    })
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->columnSpanFull(),

                // Champ « Changer l'image » : en édition il démarre vide (l'image
                // actuelle est déjà en aperçu au-dessus). Vide = conservée ;
                // fichier déposé = remplacée (variantes régénérées par l'observer).
                FileUpload::make('image_path')
                    ->label(fn (string $operation): string => $operation === 'create'
                        ? 'Image de la catégorie'
                        : "Changer l'image")
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('categories')
                    ->visibility('public')
                    ->maxSize(15360)
                    ->formatStateUsing(fn (string $operation, $state) => $operation === 'edit' ? null : $state)
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText(fn (string $operation): HtmlString => new HtmlString(
                        ($operation === 'create'
                            ? 'Facultatif. '
                            : 'Laissez vide pour conserver l\'image ci-dessus. ')
                        .'<strong>Format portrait recommandé : 1000 × 1250 px</strong> (4:5). 15 Mo maximum.'
                    ))
                    ->columnSpanFull(),

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

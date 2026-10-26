<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Content\PostResource\Pages;
use App\Models\Content\Post;
use App\Services\ImageVariantGenerator;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
 * Journal (Phase 2.2D) : la cliente écrit, illustre, programme et met des
 * produits en avant — sans toucher au code. Un article sans contenu resterait
 * une coquille vide : mieux vaut peu d'articles vivants qu'un journal
 * fantôme (cf. spec 02.2).
 */
class PostResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Content;
    }

    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Journal';

    public static function getModelLabel(): string
    {
        return 'article';
    }

    public static function getPluralModelLabel(): string
    {
        return 'articles';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('excerpt')
                    ->label('Chapeau (résumé)')
                    ->helperText('Deux phrases affichées sur la liste des articles. Si vide, le début du texte est utilisé.')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                RichEditor::make('content')
                    ->label('Texte')
                    ->helperText("Mise en forme (gras, titres, listes, liens) — les scripts sont retirés à l'affichage.")
                    ->required()
                    ->columnSpanFull(),
                FileUpload::make('cover_image_path')
                    ->label('Photo de couverture')
                    ->helperText('Affichée en tête de l\'article et sur la liste. 15 Mo maximum.')
                    ->image()
                    ->acceptedFileTypes(ImageVariantGenerator::ACCEPTED_TYPES)
                    ->disk('public')
                    ->directory('journal')
                    ->visibility('public')
                    ->maxSize(15360)
                    ->columnSpanFull(),
                Select::make('products')
                    ->label('Produits mis en avant')
                    ->helperText("Les pièces citées dans l'article, affichées en bas de page avec un lien d'achat.")
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label('Publié')
                    ->helperText("Tant que ce n'est pas coché, l'article reste invisible (brouillon).")
                    ->default(false),
                DateTimePicker::make('published_at')
                    ->label('Date de parution')
                    ->helperText('Anticipez : un article futur ne paraît que le jour dit.')
                    ->default(now()),
                TextInput::make('position')
                    ->label('Ordre')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titre')->searchable(),
                IconColumn::make('is_published')->label('Publié')->boolean(),
                TextColumn::make('published_at')->label('Parution')->dateTime('d/m/Y')->sortable(),
                TextColumn::make('products_count')->label('Produits')->counts('products'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')->label('Publié'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}

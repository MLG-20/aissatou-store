<?php

namespace App\Filament\Resources\Catalogue;

use App\Enums\Admin\AdminPermission;
use App\Enums\Catalogue\ProductLabel;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Catalogue\ProductResource\Pages;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Catalogue;
    }

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Produits';

    public static function getModelLabel(): string
    {
        return 'produit';
    }

    public static function getPluralModelLabel(): string
    {
        return 'produits';
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
                    // $state est nullable : quitter le champ encore vide (à la
                    // création) le déclenche avec null, ce qui plantait sur un
                    // paramètre typé `string`.
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug($state ?? ''))),

                TextInput::make('slug')
                    ->label('Adresse de la page (lien)')
                    ->helperText("Rempli automatiquement à partir du nom : c'est ce qui apparaît dans l'adresse du site (ex. « Turban Croisé Doré » → turban-croise-dore). Les accents et espaces sont retirés pour former une adresse valide — en général, ne pas y toucher.")
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Select::make('category_id')
                    ->label('Catégorie')
                    ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('sku')
                    ->label('Référence article')
                    // Générée par le modèle à l'enregistrement (Product::generateSku).
                    // `disabled` empêche la saisie, `dehydrated(false)` empêche
                    // que le champ écrase la valeur générée : l'admin la voit mais
                    // ne peut ni la vider ni créer un doublon.
                    ->helperText('Attribuée automatiquement à partir de la catégorie (ex. HIJ-001). Non modifiable.')
                    ->placeholder('Attribuée à l’enregistrement')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('material_tagline')
                    ->label('Matière / bénéfice')
                    ->helperText("Une phrase très courte, entre le nom et le prix : la matière, puis ce qu'elle apporte. Par exemple « Modal souple — tombé fluide » ou « Soie lavée — reflet mat ». C'est ce qui donne envie de toucher la pièce ; évitez la fiche technique.")
                    ->maxLength(120),

                Select::make('material_id')
                    ->label('Matière (filtre)')
                    ->helperText("La valeur normalisée du filtre « Matière » côté boutique — gérée dans le menu Catalogue > Matières. C'est elle qui filtre, pas la phrase ci-dessus.")
                    ->relationship('material', 'name')
                    ->searchable()
                    ->preload(),

                TextInput::make('price')
                    ->label('Prix (FCFA)')
                    ->numeric()
                    ->required()
                    ->minValue(0),

                Select::make('label')
                    ->label('Signal commercial')
                    ->options(ProductLabel::options())
                    ->helperText("À réserver aux produits dont ce signal change vraiment la décision d'achat. S'il est mis partout, il ne veut plus rien dire.")
                    ->placeholder('Aucun'),

                Toggle::make('is_active')
                    ->label('Visible sur le site')
                    ->helperText('Décochez pour retirer le produit de la boutique sans le supprimer.')
                    ->default(true),

                Textarea::make('description')
                    ->rows(4)
                    ->maxLength(5000)
                    ->columnSpanFull(),

                Repeater::make('images')
                    ->relationship('images')
                    ->label('Images')
                    // À l'ouverture d'un produit existant : on met de côté le chemin
                    // de chaque image (existing_path) et on vide le champ d'upload,
                    // qui devient un bouton « Changer l'image » ouvrant directement le
                    // sélecteur — même logique que le hero, sans supprimer d'abord.
                    ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {
                        $data['existing_path'] = $data['path'] ?? null;
                        $data['path'] = null;

                        return $data;
                    })
                    // À l'enregistrement d'une image existante : champ laissé vide =>
                    // on restaure l'ancien chemin (image conservée) ; un fichier déposé
                    // la remplace (et l'observer régénère les variantes webp).
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                        if (blank($data['path'] ?? null)) {
                            $data['path'] = $data['existing_path'] ?? null;
                        }
                        unset($data['existing_path']);

                        return $data;
                    })
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        unset($data['existing_path']);

                        return $data;
                    })
                    ->schema([
                        Hidden::make('existing_path'),

                        // Aperçu de l'image actuelle de cette ligne (édition uniquement).
                        Placeholder::make('current_image')
                            ->label('Image actuelle')
                            ->content(function (Get $get): HtmlString {
                                $path = $get('existing_path');

                                if (! $path) {
                                    return new HtmlString('<span style="color:#6b7280;">Nouvelle image à ajouter.</span>');
                                }

                                $url = Storage::disk('public')->url($path);

                                return new HtmlString(
                                    '<img src="'.e($url).'" alt="Image actuelle" '
                                    .'style="max-width:100%;max-height:160px;border-radius:0.5rem;'
                                    .'box-shadow:0 1px 3px rgba(0,0,0,.15);object-fit:cover;">'
                                );
                            })
                            ->hidden(fn (Get $get): bool => blank($get('existing_path')))
                            ->columnSpanFull(),

                        // ->image() valide le vrai type MIME côté serveur (pas juste
                        // l'extension), et Filament renomme le fichier au stockage —
                        // c'est ce qui rejette un .php déguisé en .jpg.
                        FileUpload::make('path')
                            ->label(fn (Get $get): string => blank($get('existing_path'))
                                ? 'Image'
                                : "Changer l'image")
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->maxSize(15360)
                            // Obligatoire uniquement pour une NOUVELLE image ; sur une
                            // image existante, laisser vide = conserver l'actuelle.
                            ->required(fn (Get $get): bool => blank($get('existing_path')))
                            ->helperText(fn (Get $get): HtmlString => new HtmlString(
                                (blank($get('existing_path')) ? '' : "Laissez vide pour conserver l'image ci-dessus. ")
                                .'<strong>Format carré recommandé : 1400 × 1400 px</strong>, fond clair et uni. 15 Mo maximum.'
                            ))
                            ->columnSpanFull(),

                        Toggle::make('is_primary')
                            ->label('Photo principale')
                            ->helperText('La photo affichée en premier (dans le catalogue).'),
                        TextInput::make('position')
                            ->label('Ordre')
                            ->helperText('Un plus petit nombre apparaît en premier.')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->columnSpanFull(),

                Repeater::make('variants')
                    ->relationship('variants')
                    ->label('Déclinaisons (tailles, couleurs…)')
                    ->helperText('Une ligne par variante du produit — par exemple une taille ou une couleur, avec son propre stock.')
                    ->schema([
                        TextInput::make('sku')
                            ->label('Référence de la déclinaison')
                            // Générée par le modèle (ProductVariant::generateSku),
                            // même principe que la référence article ci-dessus.
                            ->helperText('Attribuée automatiquement à partir de la référence article (ex. HIJ-001-01). Non modifiable.')
                            ->placeholder('Attribuée à l’enregistrement')
                            ->disabled()
                            ->dehydrated(false),
                        KeyValue::make('attributes')
                            ->label('Caractéristiques')
                            ->keyLabel('Type (ex. Taille)')
                            ->valueLabel('Valeur (ex. M)'),
                        TextInput::make('price_override')
                            ->label('Prix spécifique (FCFA)')
                            ->helperText('Laissez vide pour utiliser le prix du produit. À remplir seulement si cette déclinaison coûte un prix différent.')
                            ->numeric(),
                        // Phase 6B : saisi à la création seulement (stock
                        // initial journalisé) ; ensuite il ne change que par
                        // Inventaire → « Ajuster le stock » (motif + auteur).
                        TextInput::make('stock')
                            ->label(fn (?ProductVariant $record): string => $record?->exists ? 'Quantité en stock (lecture seule)' : 'Stock initial')
                            ->helperText(fn (?ProductVariant $record): ?string => $record?->exists
                                ? 'Pour corriger : Stock → Inventaire → « Ajuster le stock » (motif obligatoire, tracé).'
                                : null)
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->disabled(fn (?ProductVariant $record): bool => (bool) $record?->exists)
                            ->dehydrated(fn (?ProductVariant $record): bool => ! $record?->exists),
                        TextInput::make('low_stock_threshold')
                            ->label('Seuil d\'alerte (optionnel)')
                            ->helperText('Vide = seuil global des réglages du site.')
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('is_active')
                            ->label('Disponible')
                            ->helperText('Décochez si cette déclinaison n\'est plus vendue.')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('images.path')
                    ->label('Image')
                    ->disk('public')
                    ->limit(1),
                TextColumn::make('name')->label('Nom')->searchable(),
                TextColumn::make('category.name')->label('Catégorie')->sortable(),
                TextColumn::make('price')->label('Prix')->money('XOF')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Catégorie')
                    ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id')),
                TernaryFilter::make('is_active')->label('Active'),
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            // Affiche aussi les produits soft-supprimés (avec une action Restaurer)
            // pour que l'admin puisse récupérer un produit plutôt que de le voir
            // simplement disparaître.
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

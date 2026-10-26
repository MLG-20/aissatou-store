<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Content\HeroSlideResource\Pages;
use App\Models\Content\HeroSlide;
use App\Services\ImageVariantGenerator;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class HeroSlideResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Content;
    }

    protected static ?string $model = HeroSlide::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Hero (accueil)';

    protected static ?string $modelLabel = 'slide du hero';

    protected static ?string $pluralModelLabel = 'slides du hero';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Aperçu de l'image actuelle (édition uniquement) : la cliente
                // voit ce qui est en place avant de décider de le remplacer.
                Placeholder::make('current_image_preview')
                    ->label('Image de fond actuelle')
                    ->content(function (?HeroSlide $record): HtmlString {
                        if (! $record?->image_path) {
                            return new HtmlString(
                                '<span style="color:#6b7280;">Aucune image pour le moment — le hero affiche le fond en dégradé de la charte.</span>'
                            );
                        }

                        $url = Storage::disk('public')->url($record->image_path);

                        return new HtmlString(
                            '<img src="'.e($url).'" alt="Image de fond actuelle" '
                            .'style="max-width:100%;max-height:220px;border-radius:0.75rem;'
                            .'box-shadow:0 1px 3px rgba(0,0,0,.15);object-fit:cover;">'
                        );
                    })
                    ->hidden(fn (string $operation): bool => $operation === 'create'),

                // Champ « Changer l'image » : en édition il démarre volontairement
                // vide (l'image actuelle est déjà montrée en aperçu au-dessus).
                //  - formatStateUsing : ne pas précharger la vignette existante,
                //    pour que le champ agisse comme un bouton « déposer/remplacer »
                //    ouvrant directement le sélecteur (plus besoin de supprimer avant).
                //  - dehydrated(filled) : laissé vide => l'image existante est
                //    CONSERVÉE (le champ est ignoré à l'enregistrement) au lieu
                //    d'être effacée. Elle n'est mise à jour que si un fichier est choisi.
                FileUpload::make('image_path')
                    ->label(fn (string $operation): string => $operation === 'create'
                        ? 'Image de fond'
                        : "Changer l'image de fond")
                    ->image()
                    ->acceptedFileTypes(ImageVariantGenerator::ACCEPTED_TYPES)
                    ->imageEditor()
                    ->disk('public')
                    ->directory('hero')
                    ->visibility('public')
                    ->maxSize(15360)
                    ->formatStateUsing(fn (string $operation, $state) => $operation === 'edit' ? null : $state)
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText(fn (string $operation): HtmlString => new HtmlString(
                        ($operation === 'create'
                            ? 'Laissez vide pour garder le fond en dégradé de la charte. '
                            : 'Choisissez une image pour remplacer celle ci-dessus. Laissez vide pour la conserver. ')
                        .'<br><strong>Format paysage recommandé : 1920 × 1080 px</strong> (sujet vers le centre pour ne pas être coupé). 15 Mo maximum.'
                    )),

                Select::make('image_position')
                    ->label("Cadrage de l'image")
                    ->options([
                        'left' => 'Sujet à gauche de la photo',
                        'center' => 'Sujet centré',
                        'right' => 'Sujet à droite de la photo',
                    ])
                    ->default('center')
                    ->required()
                    ->helperText('Le bandeau est large : ce réglage évite de couper le sujet si la photo est un portrait. À ajuster selon le résultat affiché sur le site.'),

                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('subtitle')
                    ->label('Sous-titre')
                    ->maxLength(255),

                TextInput::make('cta_label')
                    ->label('Texte du bouton')
                    ->maxLength(255),

                TextInput::make('cta_url')
                    ->label('Lien du bouton')
                    ->maxLength(255)
                    ->helperText('Ex. : /catalogue ou /categories/hijabs'),

                TextInput::make('position')
                    ->label("Ordre d'affichage")
                    ->numeric()
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('image_path')->label('Image')->disk('public'),
                TextColumn::make('title')->label('Titre')->searchable(),
                TextColumn::make('position')->label('Ordre')->sortable(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageHeroSlides::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Forms\FocalPointFields;
use App\Filament\Resources\Content\HeroSlideResource\Pages;
use App\Models\Content\HeroSlide;
use App\Support\ImageSlot;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
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
                // Champ « Changer l'image » : en édition il démarre volontairement
                // vide (l'image actuelle est montrée dans l'aperçu du cadrage).
                //  - formatStateUsing : ne pas précharger la vignette existante,
                //    pour que le champ agisse comme un bouton « déposer/remplacer ».
                //  - dehydrated(filled) : laissé vide => l'image existante est
                //    CONSERVÉE au lieu d'être effacée.
                ImageSlot::Hero->configure(FileUpload::make('image_path'))
                    ->label(fn (string $operation): string => $operation === 'create'
                        ? 'Grande photo'
                        : 'Changer la grande photo')
                    ->disk('public')
                    ->directory('hero')
                    ->visibility('public')
                    ->formatStateUsing(fn (string $operation, $state) => $operation === 'edit' ? null : $state)
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText(fn (string $operation): HtmlString => ImageSlot::Hero->helperText($operation === 'create'
                        ? 'Laissez vide pour garder le fond en dégradé de la charte.'
                        : 'Laissez vide pour conserver la photo actuelle.')),

                // Le point focal garde le sujet dans le cadre sur ordinateur ET,
                // faute de version dédiée, sur téléphone.
                ...FocalPointFields::make([
                    'Ordinateur' => '12 / 5',
                    'Téléphone (sans version dédiée)' => '9 / 14',
                ]),

                Placeholder::make('current_mobile_preview')
                    ->label('Version téléphone actuelle')
                    ->content(fn (?HeroSlide $record): HtmlString => new HtmlString(
                        '<img src="'.e(Storage::disk('public')->url($record->mobile_image_path)).'" alt="" '
                        .'style="max-height:220px;border-radius:.375rem;">'
                    ))
                    ->hidden(fn (?HeroSlide $record): bool => blank($record?->mobile_image_path)),

                // Facultative mais recommandée : sur téléphone, une photo
                // paysage devient une étroite bande verticale. Une photo pensée
                // en portrait garde toute sa force.
                ImageSlot::HeroMobile->configure(FileUpload::make('mobile_image_path'))
                    ->label(fn (?HeroSlide $record): string => $record?->mobile_image_path
                        ? 'Changer la version téléphone'
                        : 'Version téléphone (facultative)')
                    ->disk('public')
                    ->directory('hero')
                    ->visibility('public')
                    ->formatStateUsing(fn (string $operation, $state) => $operation === 'edit' ? null : $state)
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText(ImageSlot::HeroMobile->helperText('Facultative : sans elle, le téléphone recadre la grande photo autour du point focal.')),

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
                ImageSlot::Hero->qualityColumn(fn (HeroSlide $slide) => $slide->image_path),
                TextColumn::make('position')->label('Ordre')->sortable(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->modalWidth('5xl'),
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

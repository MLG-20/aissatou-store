<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Content\LookResource\Pages;
use App\Models\Catalogue\Product;
use App\Models\Content\Look;
use App\Services\ImageVariantGenerator;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * « Le Look » de l'accueil. Pensé pour une utilisatrice non technicienne :
 * les points se placent avec deux curseurs (gauche → droite, haut → bas) et
 * un aperçu de la photo montre leur position en direct, numérotés comme
 * dans la liste.
 */
class LookResource extends Resource
{
    use HasSentenceCaseTitles;
    use RequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Content;
    }

    protected static ?string $model = Look::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Le Look (accueil)';

    protected static ?string $modelLabel = 'look';

    protected static ?string $pluralModelLabel = 'looks';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('La photo')
                    ->description('Une photo de mannequin qui porte plusieurs pièces de la boutique. Format portrait ou carré, sujet bien visible.')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('image_path')
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Photo' : 'Changer la photo')
                            ->image()
                            ->acceptedFileTypes(ImageVariantGenerator::ACCEPTED_TYPES)
                            ->imageEditor()
                            ->disk('public')
                            ->directory('looks')
                            ->visibility('public')
                            ->maxSize(15360)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            // Même logique que le hero : vide en édition = on garde
                            // la photo actuelle (montrée dans l'aperçu plus bas).
                            ->formatStateUsing(fn (string $operation, $state) => $operation === 'edit' ? null : $state)
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->helperText('15 Mo maximum. Laissez vide pour conserver la photo actuelle.')
                            ->columnSpanFull(),

                        TextInput::make('title')
                            ->label('Titre')
                            ->placeholder('Ex. : Soirée de Korité')
                            ->required()
                            ->maxLength(80),

                        TextInput::make('subtitle')
                            ->label('Petite phrase')
                            ->placeholder('Ex. : Un hijab satiné, un col perlé, un parfum boisé.')
                            ->maxLength(160),

                        TextInput::make('position')
                            ->label("Ordre d'affichage")
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label("Visible sur l'accueil")
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Les pièces portées')
                    ->description("Ajoutez un point par pièce visible sur la photo, puis réglez sa position avec les deux curseurs. L'aperçu se met à jour en direct.")
                    ->hidden(fn (string $operation): bool => $operation === 'create')
                    ->columns(['lg' => 5])
                    ->schema([
                        Placeholder::make('hotspot_preview')
                            ->label('Aperçu')
                            ->columnSpan(['lg' => 2])
                            ->content(fn (Get $get, ?Look $record): HtmlString => self::preview($record, $get('hotspots') ?? [])),

                        Repeater::make('hotspots')
                            ->label('Points')
                            ->relationship()
                            ->orderColumn('position')
                            ->maxItems(6)
                            ->addActionLabel('Ajouter une pièce')
                            ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'] ?? null)?->name)
                            ->columnSpan(['lg' => 3])
                            ->schema([
                                Select::make('product_id')
                                    ->label('Pièce')
                                    ->relationship('product', 'name', fn ($query) => $query->where('is_active', true))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                TextInput::make('x')
                                    ->label('Position : de gauche (0) à droite (100)')
                                    ->type('range')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(1)
                                    ->default(50)
                                    ->required()
                                    ->live(),

                                TextInput::make('y')
                                    ->label('Position : du haut (0) vers le bas (100)')
                                    ->type('range')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(1)
                                    ->default(50)
                                    ->required()
                                    ->live(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Photo du look avec les points numérotés dans l'ordre de la liste.
     *
     * @param  array<array{x?: mixed, y?: mixed}>  $hotspots
     */
    private static function preview(?Look $record, array $hotspots): HtmlString
    {
        if (! $record?->image_path) {
            return new HtmlString('<span style="color:#6b7280;">Enregistrez d\'abord une photo.</span>');
        }

        $dots = collect(array_values($hotspots))->map(function (array $spot, int $index): string {
            $x = max(0, min(100, (float) ($spot['x'] ?? 50)));
            $y = max(0, min(100, (float) ($spot['y'] ?? 50)));

            return '<span style="position:absolute;left:'.$x.'%;top:'.$y.'%;transform:translate(-50%,-50%);'
                .'width:28px;height:28px;border-radius:9999px;background:#FBF7F1;color:#4A1833;'
                .'font:600 13px/28px sans-serif;text-align:center;box-shadow:0 0 0 4px rgba(251,247,241,.45),0 2px 8px rgba(0,0,0,.35);">'
                .($index + 1).'</span>';
        })->implode('');

        return new HtmlString(
            '<div style="position:relative;display:inline-block;max-width:100%;">'
            .'<img src="'.e(Storage::disk('public')->url($record->image_path)).'" alt="" style="display:block;max-width:100%;max-height:520px;border-radius:.5rem;">'
            .$dots
            .'</div>'
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('image_path')->label('Photo')->disk('public'),
                TextColumn::make('title')->label('Titre')->searchable(),
                TextColumn::make('hotspots_count')->label('Pièces')->counts('hotspots'),
                TextColumn::make('position')->label('Ordre')->sortable(),
                IconColumn::make('is_active')->label('Visible')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLooks::route('/'),
            'create' => Pages\CreateLook::route('/creer'),
            'edit' => Pages\EditLook::route('/{record}/modifier'),
        ];
    }
}

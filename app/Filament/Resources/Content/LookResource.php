<?php

namespace App\Filament\Resources\Content;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\HasSentenceCaseTitles;
use App\Filament\Concerns\RequiresAdminPermission;
use App\Filament\Resources\Content\LookResource\Pages;
use App\Models\Catalogue\Product;
use App\Models\Content\Look;
use App\Services\VideoProcessor;
use App\Support\ImageSlot;
use App\Support\YouTube;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

    protected static ?string $navigationLabel = 'Lookbook';

    protected static ?string $modelLabel = 'look';

    protected static ?string $pluralModelLabel = 'looks';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Photo ou vidéo')
                    ->description('Un mannequin qui porte plusieurs pièces de la boutique, en pied ou à mi-corps, sujet bien visible. En vidéo : 30 secondes au plus, filmée en hauteur ; le son n\'est pas diffusé.')
                    ->columns(2)
                    ->schema([
                        Radio::make('media_type')
                            ->label('Type')
                            ->options(['image' => 'Photo', 'video' => 'Vidéo (30 s maximum)', 'youtube' => 'Lien YouTube'])
                            ->default('image')
                            ->inline()
                            ->live()
                            ->disableOptionWhen(fn (string $value): bool => $value === 'video' && ! app(VideoProcessor::class)->available())
                            ->helperText(fn (): ?string => app(VideoProcessor::class)->available()
                                ? null
                                : 'Vidéo indisponible : l\'outil de traitement vidéo (ffmpeg) n\'est pas installé sur le serveur.')
                            ->columnSpanFull(),

                        ImageSlot::Look->configure(FileUpload::make('image_path'))
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Photo' : 'Changer la photo')
                            ->disk('public')
                            ->directory('looks')
                            ->visibility('public')
                            ->visible(fn (Get $get): bool => ! in_array($get('media_type'), ['video', 'youtube'], true))
                            ->required(fn (string $operation, Get $get): bool => $operation === 'create' && $get('media_type') === 'image')
                            // Même logique que le hero : vide en édition = on garde
                            // la photo actuelle (montrée dans l'aperçu plus bas).
                            ->formatStateUsing(fn (string $operation, $state) => $operation === 'edit' ? null : $state)
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->helperText(fn (string $operation): HtmlString => ImageSlot::Look->helperText(
                                $operation === 'edit' ? 'Laissez vide pour conserver la photo actuelle.' : null
                            ))
                            ->columnSpanFull(),

                        FileUpload::make('video_source_path')
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Vidéo' : 'Changer la vidéo')
                            ->disk('public')
                            ->directory('looks/videos/sources')
                            ->visibility('public')
                            // `application/mp4` : type que le stockage temporaire
                            // de Livewire attribue à certains MP4 parfaitement valides.
                            ->acceptedFileTypes(['video/mp4', 'application/mp4', 'video/x-m4v', 'video/quicktime', 'video/webm', 'video/3gpp'])
                            ->maxSize(config('media.video_max_kilobytes'))
                            ->rules([fn (): Closure => function (string $attribute, $file, Closure $fail): void {
                                $duration = app(VideoProcessor::class)->duration($file->getRealPath());
                                $max = config('media.video_max_seconds');

                                if ($duration === null) {
                                    $fail('Cette vidéo est illisible. Envoyez-la au format MP4 ou MOV.');
                                } elseif ($duration > $max + 0.5) {
                                    $fail('Vidéo trop longue ('.(int) round($duration).' s) : '.$max.' secondes maximum. Coupez-la sur le téléphone avant de l\'envoyer.');
                                }
                            }])
                            ->visible(fn (Get $get): bool => $get('media_type') === 'video')
                            ->required(fn (string $operation, Get $get, ?Look $record): bool => $get('media_type') === 'video' && ! $record?->video_path && ! $record?->video_source_path)
                            ->formatStateUsing(fn (string $operation, $state) => $operation === 'edit' ? null : $state)
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->helperText(fn (string $operation): string => ($operation === 'edit' ? 'Laissez vide pour conserver la vidéo actuelle. ' : '')
                                .'MP4 ou MOV, 30 secondes et 60 Mo maximum. Elle est allégée automatiquement pour le site (environ une minute) ; le look apparaît sur l\'accueil dès qu\'elle est prête.')
                            ->columnSpanFull(),

                        // On garde l'identifiant, on affiche le lien : la cliente
                        // colle ce que YouTube lui donne (Short, vidéo, lien court).
                        TextInput::make('youtube_id')
                            ->label('Lien de la vidéo YouTube')
                            ->placeholder('Ex. : https://youtube.com/shorts/…')
                            ->formatStateUsing(fn (?string $state): ?string => $state ? YouTube::watchUrl($state) : null)
                            ->dehydrateStateUsing(fn (?string $state): ?string => YouTube::idFrom($state))
                            ->rules([fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                if (! YouTube::idFrom($value)) {
                                    $fail('Ce n\'est pas un lien YouTube valide. Copiez le lien depuis le bouton « Partager » de la vidéo.');
                                }
                            }])
                            ->visible(fn (Get $get): bool => $get('media_type') === 'youtube')
                            ->required(fn (Get $get): bool => $get('media_type') === 'youtube')
                            ->helperText(new HtmlString('Un Short (vertical) rend le mieux en plein écran. Il se joue sans son, en boucle. '
                                .'<strong>Uniquement les vidéos de la boutique, ou avec l\'accord écrit de leur créatrice</strong> : '
                                .'la vidéo de quelqu\'un d\'autre présentée avec nos pièces, c\'est son image utilisée pour vendre, et elle peut la retirer à tout moment.'))
                            ->columnSpanFull(),

                        Placeholder::make('youtube_preview')
                            ->label('Aperçu')
                            ->visible(fn (Get $get, ?Look $record): bool => $get('media_type') === 'youtube' && filled($record?->youtube_id))
                            ->content(fn (?Look $record): HtmlString => new HtmlString(
                                '<iframe src="'.e(YouTube::embedUrl((string) $record?->youtube_id)).'" allow="autoplay; encrypted-media" '
                                .'style="display:block;width:236px;height:420px;border:0;border-radius:.5rem;"></iframe>'
                            ))
                            ->columnSpanFull(),

                        Placeholder::make('video_state')
                            ->label('Vidéo actuelle')
                            ->visible(fn (Get $get, ?Look $record): bool => $get('media_type') === 'video' && filled($record?->video_status))
                            ->content(fn (?Look $record): HtmlString => self::videoState($record))
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

                        TextInput::make('chapter')
                            ->label('Chapitre (facultatif)')
                            ->placeholder('Ex. : Korité 2026')
                            ->helperText('Regroupe les looks d\'une même saison dans le Lookbook.')
                            ->maxLength(60),

                        TextInput::make('slug')
                            ->label('Adresse de la page')
                            ->prefix('/lookbook/')
                            ->helperText('Créée à partir du titre ; c\'est le lien à partager.')
                            ->visibleOn('edit')
                            ->required()
                            ->alphaDash()
                            ->maxLength(80)
                            ->unique(ignoreRecord: true),

                        Textarea::make('mood')
                            ->label('Texte d\'ambiance du Lookbook (facultatif)')
                            ->placeholder('Ex. : La lumière de fin d\'après-midi, une soie qui accroche le soleil…')
                            ->rows(2)
                            ->maxLength(300)
                            ->columnSpanFull(),

                        TextInput::make('position')
                            ->label("Ordre d'affichage")
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Visible sur le site (Lookbook)')
                            ->default(true)
                            ->inline(false),

                        Toggle::make('show_on_home')
                            ->label("Aussi sur l'accueil")
                            ->helperText("L'accueil en montre un aperçu ; tous les looks visibles sont dans le Lookbook.")
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

    /** État de la vidéo, en clair : en préparation, prête (avec lecteur) ou en échec. */
    private static function videoState(?Look $record): HtmlString
    {
        return match ($record?->video_status) {
            Look::VIDEO_PROCESSING => new HtmlString('<span style="color:#b45309;">En préparation… Revenez sur cette page dans une minute.</span>'),
            Look::VIDEO_FAILED => new HtmlString('<span style="color:#b91c1c;">La préparation a échoué. Renvoyez la vidéo (MP4 ou MOV).</span>'),
            Look::VIDEO_READY => new HtmlString(
                '<video src="'.e($record->videoUrl()).'" poster="'.e((string) $record->sizedUrl('medium')).'" muted loop playsinline controls '
                .'style="display:block;max-width:100%;max-height:420px;border-radius:.5rem;"></video>'
            ),
            default => new HtmlString(''),
        };
    }

    /**
     * Photo du look avec les points numérotés dans l'ordre de la liste.
     *
     * @param  array<array{x?: mixed, y?: mixed}>  $hotspots
     */
    private static function preview(?Look $record, array $hotspots): HtmlString
    {
        if (! $record?->image_path) {
            return new HtmlString('<span style="color:#6b7280;">'.($record?->isVideo()
                ? 'L\'aperçu apparaîtra quand la vidéo sera prête.'
                : 'Enregistrez d\'abord une photo.').'</span>');
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
                ImageColumn::make('image_path')->label('Aperçu')->disk('public'),
                TextColumn::make('title')->label('Titre')->searchable(),
                TextColumn::make('media_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (Look $record): string => match (true) {
                        $record->media_type === 'youtube' => 'YouTube',
                        ! $record->isVideo() => 'Photo',
                        $record->video_status === Look::VIDEO_PROCESSING => 'Vidéo en préparation',
                        $record->video_status === Look::VIDEO_FAILED => 'Vidéo en échec',
                        default => 'Vidéo',
                    })
                    ->color(fn (Look $record): string => match (true) {
                        $record->media_type === 'youtube' => 'danger',
                        ! $record->isVideo() => 'gray',
                        $record->video_status === Look::VIDEO_PROCESSING => 'warning',
                        $record->video_status === Look::VIDEO_FAILED => 'danger',
                        default => 'info',
                    }),
                // Seules les photos envoyées par la boutique relèvent de la charte :
                // l'aperçu d'une vidéo ou d'un lien YouTube n'est pas affiché en grand.
                ImageSlot::Look->qualityColumn(fn (Look $look) => $look->media_type === 'image' ? $look->image_path : null),
                TextColumn::make('hotspots_count')->label('Pièces')->counts('hotspots'),
                TextColumn::make('position')->label('Ordre')->sortable(),
                IconColumn::make('is_active')->label('Visible')->boolean(),
                IconColumn::make('show_on_home')->label('Sur l\'accueil')->boolean()->tooltip(fn (Look $record): string => $record->show_on_home ? 'Affiché aussi sur l\'accueil' : 'Seulement dans le Lookbook'),
                TextColumn::make('chapter')->label('Chapitre')->placeholder('—')->toggleable(),
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

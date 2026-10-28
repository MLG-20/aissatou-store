<?php

namespace App\Filament\Pages;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\PageRequiresAdminPermission;
use App\Models\Content\SiteSetting;
use App\Services\AdminAudit;
use App\Support\ImageSlot;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;
    use PageRequiresAdminPermission;

    protected static function requiredPermission(): AdminPermission
    {
        return AdminPermission::Settings;
    }

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Contenu du site';

    protected static ?string $navigationLabel = 'Réglages du site';

    protected static ?string $title = 'Réglages du site';

    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    /** Images des réglages : laissées vides à l'enregistrement = conservées. */
    private const IMAGE_FIELDS = ['auth_image_path'];

    public function mount(): void
    {
        $settings = SiteSetting::current()->toArray();

        // Le Repeater "simple" attend un tableau d'objets { text: ... }, pas
        // un tableau de chaînes brutes (format plus simple à parcourir côté
        // Blade public) — conversion aller-retour ici, jamais en base.
        $settings['trust_items'] = collect($settings['trust_items'] ?? [])
            ->map(fn (string $text) => ['text' => $text])
            ->all();
        $settings['marquee_items'] = collect($settings['marquee_items'] ?? [])
            ->map(fn (string $text) => ['text' => $text])
            ->all();

        // Les champs d'upload d'image démarrent vides (l'image actuelle est
        // montrée en aperçu) : le remplir = remplacer, le laisser vide = conserver
        // (voir save()). Même logique que le hero.
        foreach (self::IMAGE_FIELDS as $field) {
            $settings[$field] = null;
        }

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Bandeau de réassurance')
                    ->description("Les 3 phrases affichées sous les nouveautés de l'accueil.")
                    ->schema([
                        Repeater::make('trust_items')
                            ->label('Phrases')
                            ->simple(TextInput::make('text')->required()->maxLength(255))
                            ->maxItems(3)
                            ->minItems(1)
                            ->reorderable(false),
                    ]),

                Section::make('Bandeau défilant')
                    ->description("Une information à faire passer (promo, délai de livraison…) ? Ajoutez de courtes phrases : elles défilent sous la grande photo de l'accueil. Restez bref : 3 à 5 mots chacune.")
                    ->schema([
                        Repeater::make('marquee_items')
                            ->label('Phrases')
                            ->simple(TextInput::make('text')->required()->maxLength(60))
                            ->maxItems(6)
                            ->reorderable()
                            ->defaultItems(0)
                            ->helperText('Laissez vide pour masquer le bandeau.'),
                    ]),

                Section::make('Pages de connexion & inscription')
                    ->description('Le panneau visuel à gauche des pages « Connexion » et « Créer un compte ».')
                    ->schema([
                        Placeholder::make('auth_image_preview')
                            ->label('Image actuelle du panneau')
                            ->content(fn (): HtmlString => self::imagePreview(
                                SiteSetting::current()->auth_image_path,
                                'Aucune image — le panneau affiche le fond ébène + or de la charte.'
                            )),

                        ImageSlot::AuthPanel->configure(
                            FileUpload::make('auth_image_path'),
                            'Laissez vide pour conserver l\'image ci-dessus (ou le fond de la charte si aucune).'
                        )
                            ->label("Changer l'image du panneau")
                            ->disk('public')
                            ->directory('auth')
                            ->visibility('public'),

                        TextInput::make('auth_title')
                            ->label('Titre')
                            ->maxLength(255),

                        Textarea::make('auth_subtitle')
                            ->label('Sous-texte')
                            ->rows(2)
                            ->maxLength(500),
                    ]),

                Section::make('Accueil — section « La Maison »')
                    ->description("Les photos à côté de la citation de la marque, sur la page d'accueil : elles défilent en fondu toutes les 5 secondes. Le texte vient de « Page À propos » ci-dessous.")
                    ->schema([
                        ImageSlot::Story->configure(
                            FileUpload::make('story_images'),
                            "Jusqu'à 10 photos ; faites-les glisser pour choisir l'ordre. Sans photo, la section reprend la première photo du hero."
                        )
                            ->label('Photos')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->maxFiles(10)
                            ->panelLayout('grid')
                            ->disk('public')
                            ->directory('story')
                            ->visibility('public'),
                    ]),

                Section::make('Page À propos')
                    ->schema([
                        Textarea::make('about_story')
                            ->label('Histoire de la marque')
                            ->rows(6)
                            ->helperText('Un saut de ligne double sépare les paragraphes.'),

                        TextInput::make('about_quote')
                            ->label('Citation'),

                        Repeater::make('about_values')
                            ->label('Valeurs')
                            ->schema([
                                TextInput::make('title')->label('Titre')->required()->maxLength(255),
                                TextInput::make('text')->label('Description')->required()->maxLength(255),
                            ])
                            ->columns(2)
                            ->maxItems(4)
                            ->reorderable(false),
                    ]),

                Section::make('Coordonnées de contact')
                    ->schema([
                        TextInput::make('contact_whatsapp')->label('WhatsApp'),
                        TextInput::make('contact_email')->label('Email')->email(),
                        TextInput::make('contact_address')->label('Adresse'),
                        Textarea::make('contact_hours')->label('Horaires / livraison')->rows(3),
                    ])
                    ->columns(2),

                Section::make('Pied de page & réseaux sociaux')
                    ->description('Icônes affichées uniquement si le lien est renseigné.')
                    ->schema([
                        TextInput::make('footer_tagline')->label('Accroche sous le logo')->maxLength(255),
                        TextInput::make('social_instagram')->label('Instagram (URL)')->url()->maxLength(255),
                        TextInput::make('social_facebook')->label('Facebook (URL)')->url()->maxLength(255),
                        TextInput::make('social_tiktok')->label('TikTok (URL)')->url()->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Stock')
                    ->schema([
                        TextInput::make('low_stock_threshold')
                            ->label('Seuil d\'alerte de stock faible')
                            ->helperText('Alerte (email + badge Inventaire) quand une déclinaison descend à ce nombre ou moins. Modifiable déclinaison par déclinaison dans la fiche produit.')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $state['trust_items'] = collect($state['trust_items'] ?? [])
            ->pluck('text')
            ->filter()
            ->values()
            ->all();
        $state['marquee_items'] = collect($state['marquee_items'] ?? [])
            ->pluck('text')
            ->filter()
            ->values()
            ->all();
        // Diaporama « La Maison » : l'ordre affiché en admin est l'ordre stocké.
        $state['story_images'] = array_values($state['story_images'] ?? []);

        // Image laissée vide => on conserve l'existante (on ne l'écrase pas
        // avec null). Une nouvelle image déposée remplace.
        foreach (self::IMAGE_FIELDS as $field) {
            if (blank($state[$field] ?? null)) {
                unset($state[$field]);
            }
        }

        $settings = SiteSetting::current();
        $settings->fill($state);
        $changed = array_keys($settings->getDirty());
        $settings->save();

        if ($changed) {
            AdminAudit::record('settings.updated', $settings, ['champs' => $changed], label: 'Réglages du site');
        }

        Notification::make()->title('Réglages enregistrés')->success()->send();
    }

    private static function imagePreview(?string $path, string $empty): HtmlString
    {
        if (! $path) {
            return new HtmlString('<span style="color:#6b7280;">'.e($empty).'</span>');
        }

        $url = Storage::disk('public')->url($path);

        return new HtmlString(
            '<img src="'.e($url).'" alt="" '
            .'style="max-width:100%;max-height:220px;border-radius:0.75rem;'
            .'box-shadow:0 1px 3px rgba(0,0,0,.15);object-fit:cover;">'
        );
    }
}

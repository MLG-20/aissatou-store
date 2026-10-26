<?php

namespace App\Filament\Pages;

use App\Enums\Admin\AdminPermission;
use App\Filament\Concerns\PageRequiresAdminPermission;
use App\Models\Content\SiteSetting;
use App\Services\AdminAudit;
use App\Services\ImageVariantGenerator;
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

    public function mount(): void
    {
        $settings = SiteSetting::current()->toArray();

        // Le Repeater "simple" attend un tableau d'objets { text: ... }, pas
        // un tableau de chaînes brutes (format plus simple à parcourir côté
        // Blade public) — conversion aller-retour ici, jamais en base.
        $settings['trust_items'] = collect($settings['trust_items'] ?? [])
            ->map(fn (string $text) => ['text' => $text])
            ->all();

        // Le champ d'upload de l'image d'auth démarre vide (l'image actuelle est
        // montrée en aperçu) : le remplir = remplacer, le laisser vide = conserver
        // (voir save()). Même logique que le hero.
        $settings['auth_image_path'] = null;

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

                Section::make('Pages de connexion & inscription')
                    ->description('Le panneau visuel à gauche des pages « Connexion » et « Créer un compte ».')
                    ->schema([
                        Placeholder::make('auth_image_preview')
                            ->label('Image actuelle du panneau')
                            ->content(function (): HtmlString {
                                $path = SiteSetting::current()->auth_image_path;

                                if (! $path) {
                                    return new HtmlString(
                                        '<span style="color:#6b7280;">Aucune image — le panneau affiche le fond ébène + or de la charte.</span>'
                                    );
                                }

                                $url = Storage::disk('public')->url($path);

                                return new HtmlString(
                                    '<img src="'.e($url).'" alt="Image du panneau d\'auth" '
                                    .'style="max-width:100%;max-height:220px;border-radius:0.75rem;'
                                    .'box-shadow:0 1px 3px rgba(0,0,0,.15);object-fit:cover;">'
                                );
                            }),

                        FileUpload::make('auth_image_path')
                            ->label("Changer l'image du panneau")
                            ->image()
                            ->acceptedFileTypes(ImageVariantGenerator::ACCEPTED_TYPES)
                            ->imageEditor()
                            ->disk('public')
                            ->directory('auth')
                            ->visibility('public')
                            ->maxSize(15360)
                            ->helperText(new HtmlString(
                                'Laissez vide pour conserver l\'image ci-dessus (ou le fond de la charte si aucune). '
                                .'<strong>Format portrait recommandé : 1200 × 1600 px</strong>. 15 Mo maximum.'
                            )),

                        TextInput::make('auth_title')
                            ->label('Titre')
                            ->maxLength(255),

                        Textarea::make('auth_subtitle')
                            ->label('Sous-texte')
                            ->rows(2)
                            ->maxLength(500),
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

        // Image d'auth laissée vide => on conserve l'existante (on ne l'écrase
        // pas avec null). Une nouvelle image déposée remplace.
        if (blank($state['auth_image_path'] ?? null)) {
            unset($state['auth_image_path']);
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
}

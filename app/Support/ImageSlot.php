<?php

namespace App\Support;

use App\Services\ImageVariantGenerator;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * CHARTE PHOTO — un format par emplacement, défini ici et nulle part ailleurs.
 *
 * Le back-office (recadrage au bon format, refus des photos trop petites,
 * texte d'aide), le site (proportions d'affichage) et le badge « Photo à
 * remplacer » lisent tous ces valeurs : changer un format se fait en une
 * ligne, sans risque que l'aide dise une chose et le site en affiche une autre.
 *
 * Le minimum garantit une image nette à sa plus grande taille d'affichage
 * (pleine largeur sur grand écran pour le hero) ; l'idéal la garde nette sur
 * les écrans haute densité.
 */
enum ImageSlot: string
{
    case Hero = 'hero';
    case HeroMobile = 'hero_mobile';
    case Product = 'product';
    case Category = 'category';
    case Look = 'look';
    case PostCover = 'post_cover';
    case AuthPanel = 'auth_panel';
    case Story = 'story';

    /** @return array{0: int, 1: int} largeur, hauteur du ratio */
    public function ratio(): array
    {
        return match ($this) {
            self::Hero => [16, 9],
            self::HeroMobile, self::Category, self::Story => [4, 5],
            self::Product, self::AuthPanel => [3, 4],
            self::Look => [2, 3],
            self::PostCover => [3, 2],
        };
    }

    /** @return array{0: int, 1: int} */
    public function ideal(): array
    {
        return match ($this) {
            self::Hero => [2560, 1440],
            self::HeroMobile => [1080, 1350],
            self::Product => [1500, 2000],
            self::Category, self::Story => [1600, 2000],
            self::Look => [1600, 2400],
            self::PostCover => [2400, 1600],
            self::AuthPanel => [1200, 1600],
        };
    }

    /** @return array{0: int, 1: int} */
    public function minimum(): array
    {
        return match ($this) {
            self::Hero => [1920, 1080],
            self::HeroMobile => [900, 1125],
            // Volontairement bas : les photos de la boutique arrivent par
            // WhatsApp (compressées, ~800 px). Une fiche produit ne les
            // affiche jamais plus grand ; seules les vraies miniatures sont refusées.
            self::Product => [600, 600],
            self::Category, self::Story => [1000, 1250],
            self::Look => [1000, 1500],
            self::PostCover => [1500, 1000],
            self::AuthPanel => [900, 1200],
        };
    }

    /**
     * Le hero accepte les deux orientations : une photo paysage s'affiche en
     * plein écran, une photo portrait sur la moitié droite sur grand écran
     * (texte sur fond clair à gauche). Le recadrage forcé à l'envoi y gardait
     * une bande au centre d'un portrait, coupant les visages.
     */
    public function acceptsBothOrientations(): bool
    {
        return $this === self::Hero;
    }

    /**
     * Minimum applicable à une photo donnée : pour le hero, il dépend de son
     * orientation (un portrait n'a pas à faire 1 920 px de large).
     *
     * @return array{0: int, 1: int}
     */
    public function minimumFor(int $width, int $height): array
    {
        return $this->acceptsBothOrientations() && $height > $width
            ? [1200, 1500]
            : $this->minimum();
    }

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'la grande photo d\'accueil',
            self::HeroMobile => 'la version téléphone',
            self::Product => 'une photo produit',
            self::Category => 'une photo d\'univers ou de collection',
            self::Look => 'un look',
            self::PostCover => 'une couverture d\'article',
            self::AuthPanel => 'le panneau de connexion',
            self::Story => 'la section « La Maison »',
        };
    }

    public function orientation(): string
    {
        [$width, $height] = $this->ratio();

        return $width > $height ? 'paysage' : 'portrait';
    }

    /** Ratio au format attendu par Filament (« 16:9 »). */
    public function ratioLabel(): string
    {
        return implode(':', $this->ratio());
    }

    /** Ratio CSS (« 16 / 9 »), pour `aspect-ratio`. */
    public function cssRatio(): string
    {
        return implode(' / ', $this->ratio());
    }

    /**
     * Applique la charte à un champ d'upload : formats sûrs, recadrage
     * automatique au bon ratio (rattrapable dans l'éditeur), refus des
     * photos trop petites avec un message qui dit quoi faire, et aide claire.
     */
    public function configure(FileUpload $field, ?string $intro = null): FileUpload
    {
        $field = $field
            ->image()
            ->acceptedFileTypes(ImageVariantGenerator::ACCEPTED_TYPES)
            ->imageEditor()
            ->maxSize(15360)
            ->rules([fn (): Closure => function (string $attribute, $file, Closure $fail): void {
                $size = @getimagesize($file->getRealPath());

                if (! $size) {
                    return;
                }

                [$minWidth, $minHeight] = $this->minimumFor($size[0], $size[1]);

                if ($size[0] < $minWidth || $size[1] < $minHeight) {
                    $fail('Photo trop petite pour '.$this->label().' ('.self::px($size[0]).' × '.self::px($size[1]).' px) : '
                        .'il faut au moins '.self::px($minWidth).' × '.self::px($minHeight).' px. Elle paraîtrait floue sur le site.');
                }
            }])
            ->helperText($this->helperText($intro));

        return $this->acceptsBothOrientations()
            ? $field->imageEditorAspectRatios([$this->ratioLabel(), '4:5', null])
            : $field->imageCropAspectRatio($this->ratioLabel())->imageEditorAspectRatios([$this->ratioLabel()]);
    }

    public function helperText(?string $intro = null): HtmlString
    {
        [$idealWidth, $idealHeight] = $this->ideal();
        [$minWidth, $minHeight] = $this->minimum();

        if ($this->acceptsBothOrientations()) {
            return new HtmlString(
                ($intro ? e($intro).' ' : '')
                .'<strong>Paysage 16:9 : '.self::px($idealWidth).' × '.self::px($idealHeight).' px idéalement</strong>'
                .' (minimum '.self::px($minWidth).' × '.self::px($minHeight).' px), affichée en plein écran. '
                .'<strong>Ou portrait 4:5 : 2 000 × 2 500 px</strong> (minimum 1 200 × 1 500 px), '
                .'affichée entière à côté du texte. La photo n\'est pas recadrée ; le crayon reste disponible. JPEG, PNG ou WebP, 15 Mo maximum.'
            );
        }

        if ($this === self::Product) {
            return new HtmlString(
                ($intro ? e($intro).' ' : '')
                .'<strong>Envoyez la photo telle quelle</strong>, de préférence prise en hauteur : '
                .'elle est cadrée automatiquement, le crayon permet d\'ajuster. '
                .'Au moins '.self::px($minWidth).' × '.self::px($minHeight).' px. JPEG, PNG ou WebP.'
            );
        }

        return new HtmlString(
            ($intro ? e($intro).' ' : '')
            .'<strong>Format '.$this->orientation().' '.$this->ratioLabel().' : '
            .self::px($idealWidth).' × '.self::px($idealHeight).' px idéalement</strong>'
            .' (minimum '.self::px($minWidth).' × '.self::px($minHeight).' px). '
            .'La photo est recadrée à ce format ; le crayon permet d\'ajuster le cadrage. JPEG, PNG ou WebP, 15 Mo maximum.'
        );
    }

    /**
     * Vrai si la photo enregistrée est sous le minimum de l'emplacement :
     * les photos déjà en ligne ne sont jamais bloquées, mais signalées en
     * back-office (« Photo à remplacer »). Mémorisé par fichier et date de
     * modification : un remplacement est réévalué aussitôt.
     */
    public function isBelowMinimum(?string $path): bool
    {
        $size = self::storedSize($path);

        if (! $size) {
            return false;
        }

        [$minWidth, $minHeight] = $this->minimumFor($size[0], $size[1]);

        return $size[0] < $minWidth || $size[1] < $minHeight;
    }

    /**
     * Vrai si la photo enregistrée est plus haute que large (même mémoire
     * que isBelowMinimum) ; faux si elle est absente ou illisible.
     */
    public static function isPortrait(?string $path): bool
    {
        $size = self::storedSize($path);

        return $size !== null && $size[1] > $size[0];
    }

    /** @return array{0: int, 1: int}|null */
    private static function storedSize(?string $path): ?array
    {
        $disk = Storage::disk('public');

        if (blank($path) || ! $disk->exists($path)) {
            return null;
        }

        $size = Cache::rememberForever(
            'image_size.'.md5($path.'|'.$disk->lastModified($path)),
            fn () => @getimagesize($disk->path($path)) ?: null
        );

        return $size ? [$size[0], $size[1]] : null;
    }

    /**
     * Colonne de liste back-office : « Photo à remplacer » quand l'image en
     * ligne est sous le minimum, rien sinon.
     *
     * @param  Closure(mixed): ?string  $path  chemin de l'image de la ligne
     */
    public function qualityColumn(Closure $path): TextColumn
    {
        return TextColumn::make('image_quality')
            ->label('Photo')
            ->state(fn ($record): ?string => $this->isBelowMinimum($path($record)) ? 'À remplacer' : null)
            ->badge()
            ->color('warning')
            ->icon('heroicon-m-exclamation-triangle')
            ->tooltip('Sous la taille minimum de la charte photo : floue sur grand écran.');
    }

    private static function px(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }
}

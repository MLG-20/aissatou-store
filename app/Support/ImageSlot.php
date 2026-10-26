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

    /** @return array{0: int, 1: int} largeur, hauteur du ratio */
    public function ratio(): array
    {
        return match ($this) {
            self::Hero => [16, 9],
            self::HeroMobile, self::Category => [4, 5],
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
            self::Category => [1600, 2000],
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
            self::Product => [1050, 1400],
            self::Category => [1000, 1250],
            self::Look => [1000, 1500],
            self::PostCover => [1500, 1000],
            self::AuthPanel => [900, 1200],
        };
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
        [$minWidth, $minHeight] = $this->minimum();

        return $field
            ->image()
            ->acceptedFileTypes(ImageVariantGenerator::ACCEPTED_TYPES)
            ->imageEditor()
            ->imageCropAspectRatio($this->ratioLabel())
            ->imageEditorAspectRatios([$this->ratioLabel()])
            ->maxSize(15360)
            ->rules(["dimensions:min_width={$minWidth},min_height={$minHeight}"])
            ->validationMessages([
                'dimensions' => 'Photo trop petite pour '.$this->label().' : il faut au moins '
                    .self::px($minWidth).' × '.self::px($minHeight).' px. Elle paraîtrait floue sur le site.',
            ])
            ->helperText($this->helperText($intro));
    }

    public function helperText(?string $intro = null): HtmlString
    {
        [$idealWidth, $idealHeight] = $this->ideal();
        [$minWidth, $minHeight] = $this->minimum();

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
        $disk = Storage::disk('public');

        if (blank($path) || ! $disk->exists($path)) {
            return false;
        }

        $size = Cache::rememberForever(
            'image_size.'.md5($path.'|'.$disk->lastModified($path)),
            fn () => @getimagesize($disk->path($path)) ?: null
        );

        if (! $size) {
            return false;
        }

        [$minWidth, $minHeight] = $this->minimum();

        return $size[0] < $minWidth || $size[1] < $minHeight;
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
            ->tooltip(function (): string {
                [$width, $height] = $this->minimum();

                return 'Sous le minimum de '.self::px($width).' × '.self::px($height).' px : floue sur grand écran.';
            });
    }

    private static function px(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }
}

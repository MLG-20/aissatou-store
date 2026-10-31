<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ImageVariantGenerator
{
    /**
     * Tailles générées pour chaque image produit uploadée, en largeur maximale
     * (le ratio d'origine est conservé, pas de recadrage serveur — le cadrage
     * visuel reste géré en CSS avec `object-cover`, comme avant). Objectif :
     * ne jamais servir l'image originale (potentiellement plusieurs Mo) au
     * visiteur mobile, conformément à l'exigence de performance < 2s du
     * cahier des charges (section 4).
     *
     * @var array<string, int>
     */
    public const SIZES = [
        'thumb' => 480,
        'medium' => 800,
        'large' => 1400,
        // Grands visuels (hero, bannières, look) sur écran large ou haute
        // densité : 1400 px y paraissaient mous. Jamais d'agrandissement —
        // une photo plus petite garde sa taille d'origine.
        'xlarge' => 2400,
    ];

    /**
     * Aperçu d'un lien partagé (WhatsApp, Facebook, Instagram, TikTok) : en
     * JPEG, que toutes ces applications affichent, contrairement au WebP.
     */
    public const SHARE = 'share';

    private const SHARE_WIDTH = 1200;

    /**
     * Seuls formats acceptés à l'upload (back-office). `->image()` de
     * Filament accepte tout `image/*`, donc le SVG : un fichier qui peut
     * embarquer du JavaScript, servi depuis le domaine de la boutique.
     *
     * @var list<string>
     */
    public const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Génère les variantes WebP redimensionnées (et l'aperçu de partage JPEG) d'une image déjà stockée sur
     * le disque public, à côté du fichier original. Idempotent : régénère à
     * chaque appel (utile si l'image d'origine est remplacée).
     */
    public function generate(string $path): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return;
        }

        $manager = ImageManager::usingDriver(Driver::class);
        $source = $manager->decode($disk->path($path));

        foreach (self::SIZES as $size => $maxWidth) {
            $variant = clone $source;
            $variant->scaleDown(width: $maxWidth);

            $disk->put($this->sizedPath($path, $size), (string) $variant->encode(new WebpEncoder(quality: 80)));
        }

        $share = clone $source;
        $share->scaleDown(width: self::SHARE_WIDTH);
        $disk->put($this->sizedPath($path, self::SHARE), (string) $share->encode(new JpegEncoder(quality: 82)));
    }

    /**
     * Supprime les variantes générées pour ce chemin (l'original n'est pas
     * concerné ici — son cycle de vie est géré ailleurs).
     */
    public function forget(string $path): void
    {
        $disk = Storage::disk('public');

        foreach ([...array_keys(self::SIZES), self::SHARE] as $size) {
            $disk->delete($this->sizedPath($path, $size));
        }
    }

    public function sizedPath(string $path, string $size): string
    {
        $info = pathinfo($path);

        $directory = $info['dirname'] === '.' ? '' : $info['dirname'].'/';

        $extension = $size === self::SHARE ? 'jpg' : 'webp';

        return "{$directory}{$info['filename']}-{$size}.{$extension}";
    }
}

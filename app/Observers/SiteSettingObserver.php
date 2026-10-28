<?php

namespace App\Observers;

use App\Models\Content\SiteSetting;
use App\Services\ImageVariantGenerator;

/**
 * Diaporama « La Maison » : variantes WebP pour chaque photo ajoutée. Les
 * photos retirées gardent leur fichier (la première peut être partagée avec
 * le panneau de connexion).
 */
class SiteSettingObserver
{
    public function __construct(private readonly ImageVariantGenerator $variants) {}

    public function saved(SiteSetting $settings): void
    {
        if (! $settings->wasRecentlyCreated && ! $settings->wasChanged('story_images')) {
            return;
        }

        $before = $settings->wasRecentlyCreated ? [] : (array) $settings->getOriginal('story_images');

        foreach (array_diff((array) $settings->story_images, $before) as $path) {
            $this->variants->generate($path);
        }
    }
}

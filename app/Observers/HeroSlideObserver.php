<?php

namespace App\Observers;

use App\Models\Content\HeroSlide;
use App\Services\ImageVariantGenerator;

class HeroSlideObserver
{
    public function __construct(private readonly ImageVariantGenerator $variants) {}

    // Photo principale ET version téléphone : chacune a ses variantes.
    private const IMAGE_FIELDS = ['image_path', 'mobile_image_path'];

    public function saved(HeroSlide $slide): void
    {
        foreach (self::IMAGE_FIELDS as $field) {
            if ($slide->{$field} && ($slide->wasRecentlyCreated || $slide->wasChanged($field))) {
                $this->variants->generate($slide->{$field});
            }
        }
    }

    public function deleted(HeroSlide $slide): void
    {
        foreach (self::IMAGE_FIELDS as $field) {
            if ($slide->{$field}) {
                $this->variants->forget($slide->{$field});
            }
        }
    }
}

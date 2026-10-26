<?php

namespace App\Observers;

use App\Models\Content\Look;
use App\Services\ImageVariantGenerator;

class LookObserver
{
    public function __construct(private readonly ImageVariantGenerator $variants) {}

    public function saved(Look $look): void
    {
        if ($look->image_path && ($look->wasRecentlyCreated || $look->wasChanged('image_path'))) {
            $this->variants->generate($look->image_path);
        }
    }

    public function deleted(Look $look): void
    {
        if ($look->image_path) {
            $this->variants->forget($look->image_path);
        }
    }
}

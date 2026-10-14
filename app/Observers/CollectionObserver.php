<?php

namespace App\Observers;

use App\Models\Catalogue\Collection;
use App\Services\ImageVariantGenerator;

/** Même pipeline que catégories et hero : variantes WebP + nettoyage. */
class CollectionObserver
{
    public function __construct(private readonly ImageVariantGenerator $variants) {}

    public function saved(Collection $collection): void
    {
        if ($collection->image_path && ($collection->wasRecentlyCreated || $collection->wasChanged('image_path'))) {
            $this->variants->generate($collection->image_path);
        }
    }

    public function deleted(Collection $collection): void
    {
        if ($collection->image_path) {
            $this->variants->forget($collection->image_path);
        }
    }
}

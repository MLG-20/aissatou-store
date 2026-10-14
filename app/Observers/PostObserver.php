<?php

namespace App\Observers;

use App\Models\Content\Post;
use App\Services\ImageVariantGenerator;

/** Même pipeline que catégories, hero et collections : variantes + nettoyage. */
class PostObserver
{
    public function __construct(private readonly ImageVariantGenerator $variants) {}

    public function saved(Post $post): void
    {
        if ($post->cover_image_path && ($post->wasRecentlyCreated || $post->wasChanged('cover_image_path'))) {
            $this->variants->generate($post->cover_image_path);
        }
    }

    public function deleted(Post $post): void
    {
        if ($post->cover_image_path) {
            $this->variants->forget($post->cover_image_path);
        }
    }
}

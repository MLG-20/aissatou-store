<?php

namespace App\Observers;

use App\Jobs\ProcessLookVideo;
use App\Models\Content\Look;
use App\Services\ImageVariantGenerator;
use App\Support\YouTube;
use Illuminate\Support\Facades\Storage;

class LookObserver
{
    public function __construct(private readonly ImageVariantGenerator $variants) {}

    /** Nouvelle vidéo envoyée : « en préparation » jusqu'à la fin du traitement. */
    public function saving(Look $look): void
    {
        if ($look->isDirty('video_source_path') && $look->video_source_path) {
            $look->video_status = Look::VIDEO_PROCESSING;
            $look->video_error = null;
        }
    }

    public function saved(Look $look): void
    {
        if ($look->image_path && ($look->wasRecentlyCreated || $look->wasChanged('image_path'))) {
            $this->variants->generate($look->image_path);
        }

        if ($look->video_source_path && ($look->wasRecentlyCreated || $look->wasChanged('video_source_path'))) {
            ProcessLookVideo::dispatch($look);
        }

        // Lien YouTube : son image d'aperçu sert à l'accueil et au partage.
        if ($look->isYouTube() && ($look->wasRecentlyCreated || $look->wasChanged('youtube_id'))) {
            // Enregistrement silencieux : un `update()` relancerait cet
            // observateur, et pour un look tout juste créé, sans fin.
            if ($poster = YouTube::storePoster($look->youtube_id)) {
                $look->image_path = $poster;
                $look->saveQuietly();
                $this->variants->generate($poster);
            }
        }
    }

    public function deleted(Look $look): void
    {
        if ($look->image_path) {
            $this->variants->forget($look->image_path);
        }

        Storage::disk('public')->delete(array_filter([$look->video_path, $look->video_source_path]));
    }
}

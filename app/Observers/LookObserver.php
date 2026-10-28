<?php

namespace App\Observers;

use App\Jobs\ProcessLookVideo;
use App\Models\Content\Look;
use App\Services\ImageVariantGenerator;
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
    }

    public function deleted(Look $look): void
    {
        if ($look->image_path) {
            $this->variants->forget($look->image_path);
        }

        Storage::disk('public')->delete(array_filter([$look->video_path, $look->video_source_path]));
    }
}

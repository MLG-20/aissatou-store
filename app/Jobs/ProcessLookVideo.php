<?php

namespace App\Jobs;

use App\Models\Content\Look;
use App\Services\ImageVariantGenerator;
use App\Services\VideoProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prépare la vidéo d'un look en arrière-plan (environ 20 s pour 30 s de
 * vidéo) : version web légère + image d'aperçu. Le look n'apparaît sur
 * l'accueil qu'une fois la vidéo prête ; un échec est affiché en admin.
 */
class ProcessLookVideo implements ShouldQueue
{
    use Queueable;

    /** ffmpeg peut prendre du temps sur un petit serveur. */
    public int $timeout = 600;

    public function __construct(public Look $look) {}

    public function handle(VideoProcessor $videos, ImageVariantGenerator $images): void
    {
        $look = $this->look->fresh();
        $disk = Storage::disk('public');

        // Vidéo remplacée entre-temps, ou déjà traitée : rien à faire.
        if (! $look?->video_source_path || $look->video_status !== Look::VIDEO_PROCESSING || ! $disk->exists($look->video_source_path)) {
            return;
        }

        $name = 'looks/videos/'.Str::ulid();
        $source = $disk->path($look->video_source_path);

        try {
            $videos->transcode($source, $disk->path($name.'.mp4'));
            $videos->poster($source, $disk->path($name.'.jpg'));
        } catch (Throwable $exception) {
            $disk->delete([$name.'.mp4', $name.'.jpg']);
            $look->update(['video_status' => Look::VIDEO_FAILED, 'video_error' => mb_substr($exception->getMessage(), 0, 255)]);

            return;
        }

        $sourcePath = $look->video_source_path;
        $previousVideo = $look->video_path;
        // Ancienne image d'aperçu tirée d'une vidéo (une photo envoyée en
        // admin, elle, n'est pas supprimée).
        $previousPoster = str_starts_with((string) $look->image_path, 'looks/videos/') ? $look->image_path : null;

        $look->update([
            'video_path' => $name.'.mp4',
            'image_path' => $name.'.jpg',
            'video_status' => Look::VIDEO_READY,
            'video_error' => null,
            'video_source_path' => null,
        ]);

        // L'original (jusqu'à 60 Mo) et l'ancienne version ne servent plus.
        $disk->delete(array_filter([$sourcePath, $previousVideo, $previousPoster]));

        if ($previousPoster) {
            $images->forget($previousPoster);
        }
    }
}

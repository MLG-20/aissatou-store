<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Traitement des vidéos du Look avec ffmpeg / ffprobe : durée, version web
 * (720 px de large, H.264 sans son, lecture immédiate) et image d'aperçu.
 * Une vidéo de téléphone de 30 s passe de 20-60 Mo à quelques Mo.
 */
class VideoProcessor
{
    private ?bool $available = null;

    /** Outils présents sur le serveur ? Sinon l'option « Vidéo » est désactivée. */
    public function available(): bool
    {
        try {
            return $this->available ??= Process::run([config('media.ffmpeg'), '-version'])->successful()
                && Process::run([config('media.ffprobe'), '-version'])->successful();
        } catch (Throwable) {
            return $this->available = false;
        }
    }

    /** Durée en secondes, null si le fichier n'est pas une vidéo lisible. */
    public function duration(string $path): ?float
    {
        $result = Process::timeout(30)->run([
            config('media.ffprobe'), '-v', 'error', '-select_streams', 'v:0',
            '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $path,
        ]);

        $duration = trim($result->output());

        return $result->successful() && is_numeric($duration) ? (float) $duration : null;
    }

    /** Version web : 720 px de large au plus, sans son, coupée à la durée maximale. */
    public function transcode(string $source, string $destination): void
    {
        $this->ffmpeg([
            '-i', $source, '-t', (string) config('media.video_max_seconds'), '-an',
            '-vf', 'scale=min(720\,iw):-2', '-c:v', 'libx264', '-preset', 'slow', '-crf', '26',
            '-pix_fmt', 'yuv420p', '-movflags', '+faststart', $destination,
        ]);
    }

    /** Image d'aperçu en pleine définition, prise à 1 s (ou au début si plus courte). */
    public function poster(string $source, string $destination): void
    {
        $at = min(1.0, ($this->duration($source) ?? 0) / 2);

        $this->ffmpeg(['-ss', (string) $at, '-i', $source, '-frames:v', '1', '-q:v', '2', $destination]);
    }

    /** @param  list<string>  $arguments */
    private function ffmpeg(array $arguments): void
    {
        $result = Process::timeout(300)->run([config('media.ffmpeg'), '-v', 'error', '-y', ...$arguments]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'ffmpeg a échoué');
        }
    }
}

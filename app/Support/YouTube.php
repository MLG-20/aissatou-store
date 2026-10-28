<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Vidéos YouTube des looks : identifiant tiré de n'importe quel lien
 * (Shorts, vidéo, lien court, intégration), lecteur sans cookies
 * publicitaires, image d'aperçu copiée en local.
 */
final class YouTube
{
    /** « https://youtube.com/shorts/AbC…?si=… » → « AbC… » ; null si ce n'est pas un lien YouTube. */
    public static function idFrom(?string $url): ?string
    {
        $url = trim((string) $url);

        if (! preg_match('#^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com|youtube-nocookie\.com|youtu\.be)/#i', $url)) {
            return null;
        }

        return preg_match('#(?:[?&]v=|youtu\.be/|/shorts/|/embed/|/live/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])#', $url, $matches)
            ? $matches[1]
            : null;
    }

    public static function watchUrl(string $id): string
    {
        return 'https://www.youtube.com/watch?v='.$id;
    }

    /**
     * Lecteur muet, sans commandes ni suggestions de fin (rel=0 : seulement
     * la chaîne de la vidéo), domaine « nocookie ». En boucle par défaut.
     */
    public static function embedUrl(string $id, bool $loop = true): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$id.'?'.http_build_query([
            'autoplay' => 1, 'mute' => 1, 'controls' => 0,
            'playsinline' => 1, 'rel' => 0, 'modestbranding' => 1, 'disablekb' => 1, 'iv_load_policy' => 3,
        ] + ($loop
            ? ['loop' => 1, 'playlist' => $id]
            // Lookbook en défilement automatique : la fin de la vidéo fait
            // passer au look suivant ; la page l'apprend par messages
            // (enablejsapi), sans charger le script de l'API YouTube.
            : ['enablejsapi' => 1, 'origin' => url('/')]));
    }

    /**
     * Copie l'image d'aperçu sur le disque public et renvoie son chemin. Les
     * Shorts ont une image au format vertical (oardefault) ; sinon la plus
     * grande disponible. Null si YouTube est injoignable.
     */
    public static function storePoster(string $id): ?string
    {
        foreach (['oardefault', 'maxresdefault', 'hqdefault'] as $name) {
            try {
                $response = Http::timeout(8)->get("https://i.ytimg.com/vi/{$id}/{$name}.jpg");
            } catch (Throwable) {
                return null;
            }

            // YouTube renvoie une petite image grise (120 px) quand la taille n'existe pas.
            if ($response->successful() && strlen($response->body()) > 5000) {
                $path = "looks/youtube/{$id}.jpg";
                Storage::disk('public')->put($path, $response->body());

                return $path;
            }
        }

        return null;
    }
}

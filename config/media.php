<?php

/*
 * Vidéos du Look : durée maximale et outils de traitement. ffmpeg compresse
 * la vidéo envoyée en version web et en tire l'image d'aperçu ; sans lui
 * (hébergement qui ne le propose pas), l'option « Vidéo » est désactivée en
 * admin.
 */
return [
    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
    'video_max_seconds' => 30,
    // Taille maximale d'une vidéo envoyée, en Ko (vidéo de téléphone 30 s :
    // 20 à 60 Mo). Le php.ini du serveur doit l'accepter (upload_max_filesize,
    // post_max_size).
    'video_max_kilobytes' => 61440,
];

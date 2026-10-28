<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un look peut être une vidéo (30 s au plus) au lieu d'une photo. La vidéo
 * envoyée (`video_source_path`) est compressée en arrière-plan en version web
 * (`video_path`) ; l'image d'aperçu qui en est tirée devient `image_path`,
 * d'où la colonne désormais facultative le temps du traitement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('looks', function (Blueprint $table) {
            $table->string('media_type', 8)->default('image')->after('subtitle');
            $table->string('image_path')->nullable()->change();
            $table->string('video_source_path')->nullable()->after('image_path');
            $table->string('video_path')->nullable()->after('video_source_path');
            $table->string('video_status', 16)->nullable()->after('video_path');
            $table->string('video_error')->nullable()->after('video_status');
        });
    }

    public function down(): void
    {
        Schema::table('looks', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'video_source_path', 'video_path', 'video_status', 'video_error']);
        });
    }
};

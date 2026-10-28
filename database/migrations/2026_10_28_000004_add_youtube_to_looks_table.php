<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un look peut aussi être une vidéo YouTube (Short ou vidéo classique) :
 * seul l'identifiant est gardé ; son image d'aperçu est copiée en local
 * (`image_path`) pour l'accueil et le partage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('looks', function (Blueprint $table) {
            $table->string('youtube_id', 11)->nullable()->after('video_error');
        });
    }

    public function down(): void
    {
        Schema::table('looks', function (Blueprint $table) {
            $table->dropColumn('youtube_id');
        });
    }
};

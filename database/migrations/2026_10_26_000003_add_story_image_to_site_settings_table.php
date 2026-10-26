<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Photo propre à la section « La Maison » de l'accueil, qui reprenait jusqu'ici
 * celle du panneau de connexion. Elle démarre sur cette même image : rien ne
 * change à l'écran tant qu'on ne la remplace pas (Filament > Réglages du site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('story_image_path')->nullable()->after('auth_image_path');
        });

        DB::table('site_settings')->update(['story_image_path' => DB::raw('auth_image_path')]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('story_image_path');
        });
    }
};

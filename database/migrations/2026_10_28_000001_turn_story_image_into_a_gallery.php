<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « La Maison » passe d'une photo à un diaporama (10 photos au plus, dans
 * l'ordre choisi en admin). La photo actuelle devient la première : rien ne
 * change à l'écran tant qu'on n'en ajoute pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('story_images')->nullable()->after('story_image_path');
        });

        foreach (DB::table('site_settings')->whereNotNull('story_image_path')->get(['id', 'story_image_path']) as $row) {
            DB::table('site_settings')->where('id', $row->id)->update(['story_images' => json_encode([$row->story_image_path])]);
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('story_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('story_image_path')->nullable()->after('auth_image_path');
        });

        foreach (DB::table('site_settings')->whereNotNull('story_images')->get(['id', 'story_images']) as $row) {
            DB::table('site_settings')->where('id', $row->id)->update(['story_image_path' => json_decode($row->story_images, true)[0] ?? null]);
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('story_images');
        });
    }
};

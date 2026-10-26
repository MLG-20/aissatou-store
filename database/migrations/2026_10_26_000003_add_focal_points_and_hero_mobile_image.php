<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Charte photo : un POINT FOCAL (où se trouve le sujet, en % de la photo)
 * pour toutes les images que le site recadre selon l'écran, et une photo
 * dédiée au téléphone pour le hero.
 *
 * Le point focal remplace le réglage « sujet à gauche / centré / à droite »
 * du hero, trop grossier : il ne gérait que l'horizontale, et un recadrage
 * portrait sur téléphone coupait encore les visages.
 */
return new class extends Migration
{
    private const TABLES = ['hero_slides', 'categories', 'collections', 'posts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedTinyInteger('focal_x')->default(50);
                $table->unsignedTinyInteger('focal_y')->default(50);
            });
        }

        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('mobile_image_path')->nullable()->after('image_path');
        });

        // Reprise de l'ancien réglage : gauche / droite deviennent un point
        // focal équivalent, le reste reste centré.
        DB::table('hero_slides')->where('image_position', 'left')->update(['focal_x' => 25]);
        DB::table('hero_slides')->where('image_position', 'right')->update(['focal_x' => 75]);

        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn('image_position');
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('image_position')->default('center');
        });

        DB::table('hero_slides')->where('focal_x', '<', 40)->update(['image_position' => 'left']);
        DB::table('hero_slides')->where('focal_x', '>', 60)->update(['image_position' => 'right']);

        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn('mobile_image_path');
        });

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['focal_x', 'focal_y']);
            });
        }
    }
};

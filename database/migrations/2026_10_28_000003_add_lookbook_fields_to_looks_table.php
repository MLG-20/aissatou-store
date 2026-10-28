<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Page Lookbook : chaque look a son adresse (à partager), une phrase
 * d'ambiance, un chapitre facultatif (« Korité 2026 »…) et le choix de
 * figurer aussi sur l'accueil. Les looks existants y restent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('looks', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
            $table->string('chapter', 60)->nullable()->after('subtitle');
            $table->text('mood')->nullable()->after('chapter');
            $table->boolean('show_on_home')->default(true)->after('is_active');
        });

        foreach (DB::table('looks')->orderBy('id')->get(['id', 'title']) as $look) {
            $base = Str::slug($look->title) ?: 'look';
            $slug = $base;

            for ($i = 2; DB::table('looks')->where('slug', $slug)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }

            DB::table('looks')->where('id', $look->id)->update(['slug' => $slug]);
        }

        Schema::table('looks', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('looks', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'chapter', 'mood', 'show_on_home']);
        });
    }
};

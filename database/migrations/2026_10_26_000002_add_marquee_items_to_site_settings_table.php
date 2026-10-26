<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bandeau défilant sous le hero de l'accueil : quelques arguments courts,
 * éditables (Filament > Réglages du site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->json('marquee_items')->nullable()->after('trust_items');
        });

        DB::table('site_settings')->update(['marquee_items' => json_encode([
            'Paiement à la réception',
            'Wave, Orange Money ou carte',
            'Livraison à Dakar et en régions',
            'Une conseillère sur WhatsApp',
            'Pièces choisies une à une',
        ])]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('marquee_items');
        });
    }
};

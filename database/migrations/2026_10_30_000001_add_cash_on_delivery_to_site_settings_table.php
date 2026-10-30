<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement à la livraison activable depuis Réglages du site. Coupé par
 * défaut : au lancement, seul le paiement en ligne est proposé (pas d'espèces
 * à transporter, pas de colis refusé à la porte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('cash_on_delivery_enabled')->default(false)->after('low_stock_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('cash_on_delivery_enabled');
        });
    }
};

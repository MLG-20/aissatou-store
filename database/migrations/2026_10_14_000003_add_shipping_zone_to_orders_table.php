<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nom de la zone appliquée, figé à l'achat avec le montant (shipping_total)
 * : si l'admin reconfigure les zones après coup, la commande garde la
 * version du jour de l'achat, comme pour les prix (Phase 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shipping_zone')->nullable()->after('shipping_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('shipping_zone');
        });
    }
};

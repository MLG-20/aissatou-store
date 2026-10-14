<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode manuel de livraison (Phase 4) : l'admin assigne la commande à un
 * livreur (ou un mode : « moto », « retrait boutique »…) avec une note
 * interne, visibles ensuite par le client dans son suivi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('delivery_assignee')->nullable()->after('shipping_zone');
            $table->text('delivery_note')->nullable()->after('delivery_assignee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['delivery_assignee', 'delivery_note']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp (docs/files/08-whatsapp-cloud-api.md) :
 * - consentement porté par la commande : sans accord, aucun message ;
 * - journal des messages envoyés, mis à jour par le webhook Meta
 *   (distribué, lu, échec), pour la fiche commande du back-office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('whatsapp_opt_in')->default(false)->after('landmark');
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_opt_in');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('to', 20);
            $table->string('template', 64);
            // Identifiant renvoyé par Meta : clé des mises à jour du webhook.
            $table->string('wamid')->nullable()->unique();
            $table->string('status', 16)->index();
            $table->string('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_opt_in', 'whatsapp_opt_in_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements électroniques (Phase 5, PayTech).
 *
 * - `payments` gagne le jeton de session du fournisseur (`token`, clé de la
 *   vérification active `get-status`), le moyen réellement utilisé (Wave,
 *   Orange Money…), l'heure de confirmation et l'échéance au-delà de laquelle
 *   une commande impayée est annulée (stock remis en vente). `reference`
 *   (déjà là) reste la référence de transaction externe affichée en admin.
 * - `payment_events` est le journal financier : chaque tentative, chaque IPN
 *   (valide ou suspect), chaque vérification. Immuable — pas d'`updated_at`,
 *   le modèle refuse modification et suppression. `payment_id` est nullable :
 *   un IPN falsifié pour une commande inconnue est journalisé quand même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('token')->nullable()->unique()->after('reference');
            $table->string('method', 64)->nullable()->after('token');
            $table->timestamp('confirmed_at')->nullable()->after('method');
            $table->timestamp('expires_at')->nullable()->index()->after('confirmed_at');
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->index();
            $table->string('type', 32)->index();
            $table->string('status', 32)->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['token']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['token', 'method', 'confirmed_at', 'expires_at']);
        });
    }
};

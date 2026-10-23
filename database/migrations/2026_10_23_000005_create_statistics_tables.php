<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiques (Phase 6D).
 *
 * - `daily_visitors` : un visiteur unique par jour = une empreinte hachée
 *   avec un sel aléatoire du jour (jamais stocké en base, jamais d'IP) —
 *   impossible de relier deux jours ou de remonter à une personne. Purgé
 *   après 35 jours (seul le total quotidien est conservé).
 * - `daily_stats` : agrégats quotidiens calculés par tâche planifiée
 *   (`stats:aggregate`) — le tableau de bord ne relit jamais tout
 *   l'historique des commandes.
 * - `carts.abandoned_at` : posé par `carts:detect-abandoned`, remis à
 *   null dès que le panier change.
 * - Index pour les requêtes par période ; reprise de `confirmed_at` des
 *   paiements à la livraison déjà confirmés (la date d'encaissement fonde
 *   le chiffre d'affaires, décision Ahmed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_visitors', function (Blueprint $table): void {
            $table->date('day');
            $table->char('fingerprint', 64);
            $table->primary(['day', 'fingerprint']);
        });

        Schema::create('daily_stats', function (Blueprint $table): void {
            $table->date('day')->primary();
            $table->decimal('revenue', 14, 2)->default(0);
            $table->decimal('merchandise', 14, 2)->default(0);
            $table->unsignedInteger('paid_orders')->default(0);
            $table->unsignedInteger('placed_orders')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('abandoned_carts')->default(0);
            $table->timestamp('computed_at')->nullable();
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->timestamp('abandoned_at')->nullable()->index();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['status', 'confirmed_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index('created_at');
        });

        DB::table('payments')
            ->where('status', 'confirme')
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['status', 'confirmed_at']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropIndex(['abandoned_at']);
            $table->dropColumn('abandoned_at');
        });

        Schema::dropIfExists('daily_stats');
        Schema::dropIfExists('daily_visitors');
    }
};

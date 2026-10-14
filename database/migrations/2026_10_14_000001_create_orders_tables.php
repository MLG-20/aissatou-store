<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Domaine Commande (Phase 3) : historique immuable + paiement découplé.
 *
 * - `orders` porte le statut de commande ET le statut de paiement séparément
 *   (une commande peut être « en préparation » avec un paiement « en attente »),
 *   le total figé au moment de l'achat et l'adresse de livraison figée elle
 *   aussi (le carnet d'adresses peut évoluer après coup sans réécrire le passé).
 * - `order_items` fige nom / SKU / prix unitaire à l'achat : le prix affiché sur
 *   une commande passée ne varie jamais, même si le catalogue change ensuite.
 * - `payments` ne conserve que fournisseur, statut, référence et montant — jamais
 *   de données bancaires (cahier des charges, section 5.3 et phase 5).
 * - `order_status_history` trace qui / quand / ancien → nouveau statut, pour
 *   les litiges (exigence phase 3).
 *
 * Choix FK : `order_items.product_id` est nullable + `nullOnDelete()` — si un
 * produit est supprimé en dur, la ligne d'historique survit grâce aux colonnes
 * snapshot. `orders.user_id` suit la convention du projet (cascade, comme
 * paniers et adresses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32)->unique();
            $table->string('status', 32)->index();
            $table->string('payment_status', 32)->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('recipient_name');
            $table->string('phone');
            $table->string('city');
            $table->string('address_line');
            $table->string('landmark')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->index();
            $table->string('status', 32)->index();
            $table->string('reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('author_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

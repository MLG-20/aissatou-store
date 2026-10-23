<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes promo (Phase 6C).
 *
 * - `promo_codes` : réduction en % ou montant fixe, fenêtre de validité,
 *   minimum d'achat, plafond d'utilisations (`uses_count` incrémenté de façon
 *   atomique à la validation finale), option « une fois par client ».
 * - `promo_code_product` / `promo_code_category` : ciblage. Sans cible, le
 *   code vaut pour tout le panier ; avec, seulement pour les lignes éligibles.
 * - `promo_code_redemptions` : une ligne par commande ayant utilisé un code
 *   (unique par commande : un code par commande) ; `released_at` quand la
 *   commande est annulée — l'utilisation est alors rendue.
 * - `orders.discount_total` + `orders.promo_code` : remise FIGÉE à l'achat,
 *   comme les prix (le code peut changer ou disparaître ensuite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->string('type', 16);
            $table->decimal('value', 12, 2);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('min_subtotal', 12, 2)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('once_per_customer')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('promo_code_product', function (Blueprint $table): void {
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['promo_code_id', 'product_id']);
        });

        Schema::create('promo_code_category', function (Blueprint $table): void {
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['promo_code_id', 'category_id']);
        });

        Schema::create('promo_code_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount', 12, 2);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['promo_code_id', 'user_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('discount_total', 12, 2)->default(0)->after('subtotal');
            $table->string('promo_code', 40)->nullable()->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['discount_total', 'promo_code']);
        });

        Schema::dropIfExists('promo_code_redemptions');
        Schema::dropIfExists('promo_code_category');
        Schema::dropIfExists('promo_code_product');
        Schema::dropIfExists('promo_codes');
    }
};

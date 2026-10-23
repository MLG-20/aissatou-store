<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion fine du stock (Phase 6B).
 *
 * - `stock_movements` : journal immuable de CHAQUE variation (vente, remise
 *   en stock à l'annulation, ajustement manuel motivé, stock initial), avec
 *   le stock résultant, l'auteur et la commande liée. Référence et libellé
 *   figés : la ligne reste lisible si la déclinaison est supprimée.
 * - `product_variants.low_stock_threshold` : seuil propre à la déclinaison
 *   (null = seuil global des réglages) ; `low_stock_notified_at` : alerte
 *   déjà envoyée pour ce passage sous le seuil (remis à null en remontant).
 * - `site_settings.low_stock_threshold` : seuil global (défaut 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('label')->nullable();
            $table->string('type', 32)->index();
            $table->integer('quantity');
            $table->unsignedInteger('stock_after');
            $table->string('reason')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('stock');
            $table->timestamp('low_stock_notified_at')->nullable()->after('low_stock_threshold');
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->unsignedInteger('low_stock_threshold')->default(3);
        });

        // Point de départ du journal : le stock déjà en base devient un
        // mouvement « initial », pour que chaque quantité ait une origine.
        DB::table('stock_movements')->insertUsing(
            ['product_variant_id', 'sku', 'type', 'quantity', 'stock_after', 'reason', 'created_at'],
            DB::table('product_variants')
                ->where('stock', '>', 0)
                ->selectRaw("id, sku, 'initial', stock, stock, 'Stock existant à la mise en place du journal', ?", [now()])
        );
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('low_stock_threshold');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['low_stock_threshold', 'low_stock_notified_at']);
        });

        Schema::dropIfExists('stock_movements');
    }
};

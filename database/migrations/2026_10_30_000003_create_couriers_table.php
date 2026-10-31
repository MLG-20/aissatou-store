<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Livreurs de la boutique (internes ou services extérieurs). La commande
 * garde le livreur choisi ; l'historique des statuts note le livreur qui a
 * validé depuis sa page, à côté de l'admin.
 *
 * Page « Ma tournée » : seule l'empreinte SHA-256 du lien personnel est
 * gardée, le lien lui-même n'est montré qu'à l'envoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('type', 16);
            $table->boolean('is_active')->default(true);
            $table->string('tour_token_hash', 64)->nullable()->unique();
            $table->timestamp('tour_token_issued_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('courier_id')->nullable()->after('delivery_assignee')->constrained()->nullOnDelete();
        });

        Schema::table('order_status_history', function (Blueprint $table): void {
            $table->foreignId('courier_id')->nullable()->after('author_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_status_history', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('courier_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('courier_id');
        });

        Schema::dropIfExists('couriers');
    }
};

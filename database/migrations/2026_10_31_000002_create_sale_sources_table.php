<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance des ventes (Visibilité V4) : les liens de suivi gérés en admin,
 * la source retenue sur chaque commande et sur le compteur de visiteurs
 * anonyme. Les sources de base sont fournies, la cliente ajoute les siennes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('sale_source_id')->nullable()->after('courier_id')->constrained()->nullOnDelete();
        });

        Schema::table('daily_visitors', function (Blueprint $table): void {
            $table->foreignId('sale_source_id')->nullable()->after('fingerprint')->constrained()->nullOnDelete();
        });

        $now = now();

        DB::table('sale_sources')->insert(array_map(fn (array $source): array => $source + [
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['name' => 'TikTok', 'code' => 'tiktok'],
            ['name' => 'Instagram', 'code' => 'instagram'],
            ['name' => 'Facebook', 'code' => 'facebook'],
            ['name' => 'Statuts WhatsApp', 'code' => 'whatsapp'],
            ['name' => 'Google', 'code' => 'google'],
        ]));
    }

    public function down(): void
    {
        Schema::table('daily_visitors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sale_source_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sale_source_id');
        });

        Schema::dropIfExists('sale_sources');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matière administrable (Phase 2.2) : la phrase éditoriale libre
 * (`products.material`, niveau obligatoire de la hiérarchie p.3) devient
 * `material_tagline`, et le FILTRE s'appuie sur une référence normalisée
 * (`materials` + `products.material_id`).
 *
 * Les deux coexistent comme l'exige l'audit 02.1 : la phrase donne envie de
 * toucher, la référence permet de filtrer sans doublons (« Soie » vs « soie »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('material', 'material_tagline');
            $table->foreignId('material_id')->nullable()->after('material_tagline')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('material_id');
            $table->renameColumn('material_tagline', 'material');
        });

        Schema::dropIfExists('materials');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zones de livraison (Phase 4) : frais + délai estimé par ville, gérés en
 * back-office et appliqués automatiquement dans le tunnel.
 *
 * - `city` = ville couverte, unique ; `city = null` = zone par défaut
 *   (« Autres zones »), utilisée quand aucune zone ne correspond à la ville
 *   de l'adresse (saisie libre côté client, donc jamais de correspondance
 *   garantie).
 * - La comparaison se fait sur ville normalisée (minuscules, sans accents)
 *   côté code : l'admin saisit « Dakar », le client peut écrire « dakar ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable()->unique();
            $table->decimal('fee', 12, 2)->default(0);
            $table->string('delay_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};

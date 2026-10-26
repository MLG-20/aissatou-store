<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Le Look » (accueil) : une photo de mise en situation sur laquelle des
 * points renvoient aux pièces portées. La position d'un point est stockée en
 * POURCENTAGE de la photo (0-100), pour rester juste quelle que soit la taille
 * d'écran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('looks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image_path');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('look_hotspots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('look_id')->constrained()->cascadeOnDelete();
            // Supprimer un produit retire son point, pas le look entier.
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('x', 5, 2);
            $table->decimal('y', 5, 2);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('look_hotspots');
        Schema::dropIfExists('looks');
    }
};

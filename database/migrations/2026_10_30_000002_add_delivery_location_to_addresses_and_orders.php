<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Position exacte de livraison, facultative : point GPS partagé par le
 * téléphone de la cliente (avec sa précision en mètres) ou lien Google/Apple
 * Maps collé tel quel. Sur l'adresse du carnet, recopiée dans la commande
 * comme le repère.
 */
return new class extends Migration
{
    private const TABLES = ['customer_addresses', 'orders'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->decimal('latitude', 10, 7)->nullable()->after('landmark');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                $table->unsignedInteger('location_accuracy')->nullable()->after('longitude');
                $table->string('maps_link', 500)->nullable()->after('location_accuracy');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['latitude', 'longitude', 'location_accuracy', 'maps_link']);
            });
        }
    }
};

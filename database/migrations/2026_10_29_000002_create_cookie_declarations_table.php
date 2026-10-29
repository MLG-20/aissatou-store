<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des cookies et traceurs, affiché sur la page « Politique cookies ».
 * Il démarre avec les seuls cookies que le site pose aujourd'hui, tous
 * nécessaires ; un outil ajouté plus tard (mesure d'audience…) s'y déclare
 * depuis l'admin, sans toucher au code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_declarations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider');
            $table->string('category');
            $table->text('purpose');
            $table->string('duration');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $cookies = [
            ['name' => config('session.cookie'), 'purpose' => 'Garde votre session ouverte de page en page : panier, connexion, choix en cours.', 'duration' => 'Session (2 heures sans activité)'],
            ['name' => 'XSRF-TOKEN', 'purpose' => 'Sécurité : empêche qu\'un autre site envoie des formulaires à votre place.', 'duration' => 'Session (2 heures sans activité)'],
            ['name' => 'remember_web_…', 'purpose' => 'Vous garde connectée si vous cochez « Se souvenir de moi ».', 'duration' => '400 jours'],
        ];

        foreach ($cookies as $position => $cookie) {
            DB::table('cookie_declarations')->insert($cookie + [
                'provider' => 'Aissatou Store',
                'category' => 'necessaire',
                'is_active' => true,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_declarations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brouillons des pages légales (database/data/legal), à relire puis publier
 * depuis l'admin. Les passages entre crochets attendent une décision de la
 * boutique (délai de retour, TVA…) ; une page déjà rédigée n'est pas touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (glob(database_path('data/legal/*.html')) as $file) {
            DB::table('legal_pages')
                ->where('document', basename($file, '.html'))
                ->whereNull('content')
                ->update(['content' => file_get_contents($file), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Les textes ont pu être retouchés en admin depuis : on ne les efface pas.
    }
};

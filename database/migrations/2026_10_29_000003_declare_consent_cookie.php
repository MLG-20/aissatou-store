<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le bandeau de consentement retient le choix du visiteur dans un cookie :
 * nécessaire lui aussi, donc déclaré au registre comme les autres.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cookie_declarations')->insert([
            'name' => 'aissatou_consent',
            'provider' => 'Aissatou Store',
            'category' => 'necessaire',
            'purpose' => 'Retient vos choix sur les cookies, pour ne pas vous les redemander à chaque page.',
            'duration' => '6 mois',
            'is_active' => true,
            'position' => (int) DB::table('cookie_declarations')->max('position') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('cookie_declarations')->where('name', 'aissatou_consent')->delete();
    }
};

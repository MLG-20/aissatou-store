<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reprend la page À propos restée à l'ancienne marque.
 *
 * Même cause que pour le hero (migration rebrand_hero_slides) : le renommage
 * vers Aissatou Store n'avait touché que les valeurs par défaut du code, pas
 * la ligne site_settings déjà saisie en base. Le texte affiché commençait donc
 * encore par l'ancien positionnement (« Aissatou Stor est né… », avec coquille).
 *
 * Les textes de remplacement ne sont pas inventés : ce sont mot pour mot ceux
 * de SiteSetting::current(), eux-mêmes repris du positionnement officiel
 * (rapport d'identité p.2 : promesse, piliers Pudeur/Choix/Trace).
 *
 * Portée volontairement étroite et idempotente : seules les lignes portant
 * encore les anciennes valeurs sont reprises. Un contenu que la cliente aura
 * réécrit entre-temps n'est pas touché.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')
            ->where('about_story', 'like', 'Aissatou Stor est né%')
            ->update([
                'about_story' => "Aissatou Store sélectionne des hijabs, foulards, cols, parfums et objets à offrir qui accompagnent la silhouette sans la surcharger. La valeur de la marque ne repose pas sur l'abondance, mais sur le discernement : moins d'objets, mieux choisis, mieux présentés.\n\nChaque pièce est pensée pour une femme attentive à la qualité, au tombé et à la cohérence — une élégance portable, faite d'objets choisis plutôt que d'une accumulation de références.",
                'updated_at' => now(),
            ]);

        DB::table('site_settings')
            ->where('about_quote', "L'élégance ne se choisit pas, elle se porte avec soi.")
            ->update([
                'about_quote' => "Sélectionner, c'est donner du sens.",
                'updated_at' => now(),
            ]);

        DB::table('site_settings')
            ->where('about_values', 'like', '%Authenticité%')
            ->update([
                'about_values' => json_encode([
                    ['title' => 'Pudeur', 'text' => 'Une élégance mesurée, pensée pour accompagner plutôt que démontrer.'],
                    ['title' => 'Choix', 'text' => 'La sélection devient un service et un signe de confiance.'],
                    ['title' => 'Trace', 'text' => 'Chaque objet laisse une présence discrète et mémorable.'],
                ]),
                'updated_at' => now(),
            ]);

        DB::table('site_settings')
            ->whereNull('footer_tagline')
            ->update([
                'footer_tagline' => "L'élégance voilée, pensée pour vous.",
                'updated_at' => now(),
            ]);

        DB::table('site_settings')
            ->where('contact_email', 'contact@amiras.test')
            ->update([
                'contact_email' => 'contact@aissatoustore.test',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Reprise éditoriale : pas de retour arrière destructeur.
    }
};

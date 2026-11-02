<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('seo_home_title', 70)->default('Aissatou Store — Hijabs, foulards et parfums au Sénégal');
            $table->string('seo_home_description', 160)->default('Hijabs, foulards, cols, parfums et sacs choisis avec soin. Commandez en ligne et faites-vous livrer au Sénégal.');
            $table->string('seo_catalogue_description', 160)->default('Tout le catalogue Aissatou Store : hijabs, foulards, cols, parfums et sacs, livrés au Sénégal.');
            $table->string('seo_collections_description', 160)->default('Les collections Aissatou Store : des pièces choisies et réunies par thème, pour chaque occasion.');
            $table->string('seo_lookbook_description', 160)->default('Les looks Aissatou Store : hijabs, foulards et pièces choisies, portés et mis en scène.');
            $table->string('seo_journal_description', 160)->default('Le journal Aissatou Store : conseils de style, matières et idées pour porter le hijab au quotidien.');
            $table->string('seo_about_description', 160)->default("L'histoire d'Aissatou Store, boutique de hijabs, foulards et parfums au Sénégal.");
            $table->string('seo_contact_description', 160)->default('Contactez Aissatou Store : une question sur un article, une commande ou une livraison ? Nous vous répondons.');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'seo_home_title', 'seo_home_description', 'seo_catalogue_description', 'seo_collections_description',
                'seo_lookbook_description', 'seo_journal_description', 'seo_about_description', 'seo_contact_description',
            ]);
        });
    }
};

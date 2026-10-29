<?php

use App\Enums\Content\LegalDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pages légales éditables en admin, et identité légale de la boutique dans les
 * réglages (reprise dans les textes via des repères comme {ninea}). Les quatre
 * pages existent d'office, en brouillon : rien n'est public avant relecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('document')->unique();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        foreach (LegalDocument::cases() as $document) {
            DB::table('legal_pages')->insert([
                'document' => $document->value,
                'title' => $document->title(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('legal_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('legal_ninea')->nullable();
            $table->string('legal_rccm')->nullable();
            $table->string('legal_address')->nullable();
            $table->string('legal_publisher')->nullable();
            $table->string('legal_host')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'legal_form', 'legal_ninea', 'legal_rccm',
                'legal_address', 'legal_publisher', 'legal_host',
            ]);
        });

        Schema::dropIfExists('legal_pages');
    }
};

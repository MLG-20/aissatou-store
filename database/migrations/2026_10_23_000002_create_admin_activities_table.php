<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'activité du back-office (Phase 6A) : qui (compte précis, jamais
 * un compte générique), quoi, sur quoi, quand, depuis où. Immuable — pas
 * d'`updated_at`, le modèle refuse modification et suppression.
 *
 * `admin_name` fige le nom au moment de l'action : la ligne reste lisible
 * même si le compte est renommé ou supprimé plus tard (`admin_id` → null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name')->nullable();
            $table->string('action', 64)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activities');
    }
};

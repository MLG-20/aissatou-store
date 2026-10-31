<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Client absent » signalé par le livreur : la commande reste expédiée, la
 * boutique est alertée et recontacte la cliente. On garde la dernière
 * tentative manquée et leur nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('failed_delivery_at')->nullable()->after('courier_id');
            $table->string('failed_delivery_by')->nullable()->after('failed_delivery_at');
            $table->unsignedSmallInteger('failed_delivery_attempts')->default(0)->after('failed_delivery_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['failed_delivery_at', 'failed_delivery_by', 'failed_delivery_attempts']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kecepatan angin pengamatan manual berisi bacaan counter anemometer yang terus naik
 * (mis. pos Sempor 2.158.290; kolom lama float). decimal(8,2) memotongnya menjadi 999999.99.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengamatan_klimatologi', function (Blueprint $table) {
            $table->decimal('kecepatan_angin', 14, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pengamatan_klimatologi', function (Blueprint $table) {
            $table->decimal('kecepatan_angin', 8, 2)->nullable()->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tatonas mengirim besaran klimatologi dengan 3 desimal (mis. kelembapan 87.123) dan tabel lama
 * (float) menyimpannya utuh; kolom 2 desimal membulatkannya. Setelah migrasi ini jalankan ulang
 * `sih3:import --fresh` agar data impor terisi dengan presisi penuh.
 */
return new class extends Migration
{
    private const KOLOM = [
        'kecepatan_angin' => 9, 'arah_angin' => 7, 'radiasi' => 11, 'tekanan' => 11, 'kelembapan' => 7,
        'suhu' => 7, 'uv' => 7, 'penguapan' => 9, 'hujan' => 9,
    ];

    public function up(): void
    {
        Schema::table('telemetri_klimatologi', function (Blueprint $table) {
            foreach (self::KOLOM as $kolom => $panjang) {
                $table->decimal($kolom, $panjang, 3)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('telemetri_klimatologi', function (Blueprint $table) {
            foreach (self::KOLOM as $kolom => $panjang) {
                $table->decimal($kolom, $panjang - 1, 2)->nullable()->change();
            }
        });
    }
};

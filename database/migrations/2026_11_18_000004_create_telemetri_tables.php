<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data telemetri dari semua vendor dalam satu tabel per jenis besaran.
 *
 * Asal (9 tabel per vendor):
 *   ARR : ana_higertech_arr, ana_arr_tatonas, ana_telemetri_arr (PJT), ana_arr_beacon
 *   AWLR: ana_higertech_awlr, ana_awlr_tatonas, ana_awlr_stesy, ana_awlr_login, ana_telemetri_awlr (PJT)
 *   Klim: ana_klimatologi_tatonas
 *
 * ana_arr_all & ana_awlr_all TIDAK diimpor: nilainya rusak karena setiap cron menambahkan ulang
 * data yang sama (ARR Higertech ~100x lipat). Rekap per jam dihitung ulang dari data mentah.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pemetaan perangkat vendor -> pos. Menggantikan pos_id hard-coded di controller lama
        // dan kolom ref_pos.pos_tele / pos_sensor / pos_last_get / pos_last_val.
        Schema::create('perangkat_telemetri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->constrained('pos')->cascadeOnDelete();
            $table->string('sumber', 20)->comment('App\Enums\SumberTelemetri');
            $table->string('kode_perangkat', 100)->comment('ID logger/hardware/nama stasiun di sistem vendor');
            $table->string('sensor', 50)->nullable();
            $table->decimal('faktor_skala', 12, 6)->default(1)
                ->comment('Pengali nilai mentah -> satuan baku (mis. PJT kirim cm: 0.01)');
            $table->boolean('aktif')->default(true);
            $table->timestamp('terakhir_diterima_at')->nullable();
            $table->decimal('nilai_terakhir', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['sumber', 'kode_perangkat', 'pos_id']);
        });

        Schema::create('telemetri_arr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->constrained('pos')->restrictOnDelete();
            $table->string('sumber', 20);
            $table->dateTime('waktu');
            $table->decimal('hujan', 8, 2)->nullable()->comment('mm pada interval pembacaan');
            $table->decimal('hujan_kumulatif', 10, 2)->nullable();
            $table->timestamp('diterima_at')->useCurrent();

            $table->unique(['pos_id', 'waktu', 'sumber']);
            $table->index('waktu');
        });

        Schema::create('telemetri_awlr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->constrained('pos')->restrictOnDelete();
            $table->string('sumber', 20);
            $table->dateTime('waktu');
            $table->decimal('tma', 12, 3)->nullable()->comment('Tinggi muka air / muka air tanah, nilai mentah vendor');
            $table->decimal('level', 12, 3)->nullable();
            $table->decimal('debit', 12, 3)->nullable();
            $table->timestamp('diterima_at')->useCurrent();

            $table->unique(['pos_id', 'waktu', 'sumber']);
            $table->index('waktu');
        });

        Schema::create('telemetri_klimatologi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->constrained('pos')->restrictOnDelete();
            $table->string('sumber', 20);
            $table->dateTime('waktu');
            $table->decimal('kecepatan_angin', 8, 2)->nullable();
            $table->decimal('arah_angin', 6, 2)->nullable();
            $table->decimal('radiasi', 10, 2)->nullable();
            $table->decimal('tekanan', 10, 2)->nullable();
            $table->decimal('kelembapan', 6, 2)->nullable();
            $table->decimal('suhu', 6, 2)->nullable();
            $table->decimal('uv', 6, 2)->nullable();
            $table->decimal('penguapan', 8, 2)->nullable();
            $table->decimal('hujan', 8, 2)->nullable();
            $table->timestamp('diterima_at')->useCurrent();

            $table->unique(['pos_id', 'waktu', 'sumber']);
            $table->index('waktu');
        });

        // Rekap per jam, selalu dihitung ulang dari data mentah (SUM/AVG), tidak ditambah inkremental.
        Schema::create('rekap_arr_per_jam', function (Blueprint $table) {
            $table->foreignId('pos_id')->constrained('pos')->cascadeOnDelete();
            $table->dateTime('jam')->comment('Awal jam, mis. 2026-10-17 09:00:00');
            $table->decimal('hujan', 8, 2)->nullable();
            $table->unsignedSmallInteger('jumlah_data');
            $table->timestamp('diperbarui_at')->useCurrent();

            $table->primary(['pos_id', 'jam']);
            $table->index('jam');
        });

        Schema::create('rekap_awlr_per_jam', function (Blueprint $table) {
            $table->foreignId('pos_id')->constrained('pos')->cascadeOnDelete();
            $table->dateTime('jam');
            $table->decimal('tma_rata', 12, 3)->nullable();
            $table->decimal('tma_min', 12, 3)->nullable();
            $table->decimal('tma_max', 12, 3)->nullable();
            $table->unsignedSmallInteger('jumlah_data');
            $table->timestamp('diperbarui_at')->useCurrent();

            $table->primary(['pos_id', 'jam']);
            $table->index('jam');
        });

        // Log kegagalan/payload penerimaan data. Asal: ana_data_putlog (kosong)
        Schema::create('log_telemetri', function (Blueprint $table) {
            $table->id();
            $table->string('sumber', 20)->nullable();
            $table->string('kode_perangkat', 100)->nullable();
            $table->integer('kode_status')->nullable();
            $table->string('pesan')->nullable();
            $table->longText('payload')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_telemetri');
        Schema::dropIfExists('rekap_awlr_per_jam');
        Schema::dropIfExists('rekap_arr_per_jam');
        Schema::dropIfExists('telemetri_klimatologi');
        Schema::dropIfExists('telemetri_awlr');
        Schema::dropIfExists('telemetri_arr');
        Schema::dropIfExists('perangkat_telemetri');
    }
};

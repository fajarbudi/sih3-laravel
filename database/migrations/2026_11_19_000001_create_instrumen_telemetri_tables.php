<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bacaan telemetri yang tidak cocok dengan telemetri_arr/awlr/klimatologi:
 * instrumen bendungan (piezometer, V-notch, crack), debit, bukaan katup, volume waduk, dll.
 *
 * Asal: SCADASH Bendungan Sermo. Aplikasi lama tidak menyimpannya di db_sih3
 * (tabel data_sermo_* diisi aplikasi hidrologi terpisah).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per kanal sensor vendor. Metadata (label, satuan, batas alarm) diperbarui dari API.
        Schema::create('parameter_instrumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->nullable()->constrained('pos')->nullOnDelete()
                ->comment('Opsional: instrumen bendungan tidak selalu punya pos sendiri');
            $table->string('sumber', 20)->comment('App\Enums\SumberTelemetri');
            $table->string('kode_perangkat', 100);
            $table->string('kode_parameter', 100);
            $table->string('tipe_sensor', 30)->nullable()->comment('Kategori vendor: vno, pzo, crack, flow, awlr');
            $table->string('grup', 50)->nullable();
            $table->string('label', 150)->nullable();
            $table->string('keterangan', 150)->nullable();
            $table->string('satuan', 30)->nullable();
            $table->json('batas_alarm')->nullable();
            $table->boolean('tampil')->default(true)->comment('Penanda tampil dari vendor (is_show)');
            $table->boolean('aktif')->default(true);
            $table->dateTime('terakhir_diterima_at')->nullable();
            $table->decimal('nilai_terakhir', 18, 4)->nullable();
            $table->timestamps();

            $table->unique(['sumber', 'kode_perangkat', 'kode_parameter']);
        });

        Schema::create('telemetri_instrumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_instrumen_id')->constrained('parameter_instrumen')->cascadeOnDelete();
            $table->dateTime('waktu');
            $table->decimal('nilai', 18, 4);
            $table->timestamp('diterima_at')->useCurrent();

            $table->unique(['parameter_instrumen_id', 'waktu']);
            $table->index('waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetri_instrumen');
        Schema::dropIfExists('parameter_instrumen');
    }
};

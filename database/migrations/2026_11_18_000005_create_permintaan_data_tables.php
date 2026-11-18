<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layanan permintaan data oleh publik + survei kepuasan (SKM).
 *
 * Asal: ana_data_download (satu baris per pos, identitas pemohon diulang di tiap baris,
 * dikelompokkan oleh download_sess) dipecah menjadi:
 *   permintaan_data      : satu pengajuan (identitas pemohon, tujuan, periode)
 *   permintaan_data_pos  : pos yang diminta, masing-masing punya status persetujuan sendiri
 *                          karena tiap pos bisa milik walidata berbeda.
 * ana_data_download_file (kosong) dilebur menjadi kolom token/jumlah unduh di permintaan_data_pos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->string('kode_sesi', 30)->nullable()->unique()->comment('Asal: download_sess');
            $table->string('jenis', 100)->nullable();
            $table->string('nama_pemohon', 150);
            $table->string('instansi', 150)->nullable();
            $table->string('jabatan', 150)->nullable();
            $table->string('tujuan')->nullable();
            $table->date('periode_awal')->nullable();
            $table->date('periode_akhir')->nullable();
            $table->timestamps();
        });

        Schema::create('permintaan_data_pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_data_id')->constrained('permintaan_data')->cascadeOnDelete();
            $table->foreignId('pos_id')->constrained('pos')->restrictOnDelete();
            $table->string('status', 20)->default('pengajuan')
                ->comment('App\Enums\StatusPermintaan: pengajuan, disetujui, ditolak, ditutup');
            $table->string('keterangan')->nullable();
            $table->string('path_file')->nullable();
            $table->char('token_unduh', 32)->nullable()->unique();
            $table->unsignedInteger('jumlah_unduh')->default(0);
            $table->foreignId('diproses_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();

            $table->unique(['permintaan_data_id', 'pos_id']);
            $table->index('status');
        });

        // Asal: ana_data_skm. Jawaban skm_8..skm_18 disimpan sebagai JSON {"8": "...", ...}
        // agar jumlah/urutan pertanyaan bisa berubah tanpa migration.
        Schema::create('survei_kepuasan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_data_id')->nullable()->constrained('permintaan_data')->nullOnDelete();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('walidata_id')->nullable()->constrained('walidata')->nullOnDelete();
            $table->string('jenis', 50)->nullable();
            $table->string('telepon', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->unsignedTinyInteger('usia')->nullable();
            $table->string('jenis_kelamin', 20)->nullable();
            $table->string('pendidikan', 150)->nullable();
            $table->json('jawaban')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survei_kepuasan');
        Schema::dropIfExists('permintaan_data_pos');
        Schema::dropIfExists('permintaan_data');
    }
};

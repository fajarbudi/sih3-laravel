<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data hasil pengamatan manual (diinput petugas/admin).
 *
 * Perubahan dari skema lama:
 * - Kolom *_date dan *_jam dibuang: cukup `waktu` (turunkan dengan DATE()/HOUR() atau di model).
 * - *_waktu_input menjadi created_at.
 * - *_status 'Ya'/'Tidak' menjadi boolean `tervalidasi`.
 * - Unik per (pos_id, waktu); duplikat lama dibersihkan saat impor (baris terbaru dipakai).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Asal: ana_data_arr
        Schema::create('pengamatan_arr', function (Blueprint $table) {
            $table->id();
            $this->kolomUmum($table);
            $table->decimal('hujan', 8, 2)->nullable()->comment('mm');
            $table->string('keterangan')->nullable();
            $table->boolean('tervalidasi')->default(true);
            $table->timestamps();
            $table->unique(['pos_id', 'waktu']);
        });

        // Asal: ana_data_awlr (ana_data_awlr2 kosong & tidak dipakai, dibuang)
        Schema::create('pengamatan_awlr', function (Blueprint $table) {
            $table->id();
            $this->kolomUmum($table);
            $table->decimal('tma', 10, 3)->nullable()->comment('Tinggi muka air rerata, m (asal: awlr_rerata_tma)');
            $table->decimal('limpas', 10, 3)->nullable();
            $table->decimal('intake_kiri', 10, 3)->nullable();
            $table->decimal('intake_kanan', 10, 3)->nullable();
            $table->decimal('debit_pdam', 10, 3)->nullable();
            $table->decimal('pembilas_kiri', 10, 3)->nullable();
            $table->decimal('pembilas_kanan', 10, 3)->nullable();
            $table->decimal('debit_suplesi', 10, 3)->nullable();
            $table->string('keterangan')->nullable();
            $table->boolean('tervalidasi')->default(true);
            $table->timestamps();
            $table->unique(['pos_id', 'waktu']);
        });

        // Asal: ana_data_debit
        Schema::create('pengamatan_debit', function (Blueprint $table) {
            $table->id();
            $this->kolomUmum($table);
            $table->decimal('intake_kanan', 10, 3)->nullable();
            $table->decimal('intake_kiri', 10, 3)->nullable();
            $table->decimal('limpas', 10, 3)->nullable();
            $table->decimal('debit', 10, 3)->nullable()->comment('m3/detik');
            $table->string('keterangan')->nullable();
            $table->boolean('tervalidasi')->default(true);
            $table->timestamps();
            $table->unique(['pos_id', 'waktu']);
        });

        // Asal: ana_data_klimat
        Schema::create('pengamatan_klimatologi', function (Blueprint $table) {
            $table->id();
            $this->kolomUmum($table);
            $table->decimal('kelembapan', 6, 2)->nullable()->comment('RH %');
            $table->decimal('suhu_min', 6, 2)->nullable();
            $table->decimal('suhu_max', 6, 2)->nullable();
            $table->decimal('suhu_rata', 6, 2)->nullable();
            $table->decimal('suhu_air', 6, 2)->nullable();
            $table->decimal('suhu_air_min', 6, 2)->nullable();
            $table->decimal('suhu_air_max', 6, 2)->nullable();
            $table->decimal('penguapan', 8, 2)->nullable();
            $table->decimal('kecepatan_angin', 8, 2)->nullable();
            $table->decimal('radiasi', 10, 2)->nullable();
            $table->decimal('penyinaran', 6, 2)->nullable();
            $table->decimal('hujan', 8, 2)->nullable();
            $table->string('keterangan')->nullable();
            $table->boolean('tervalidasi')->default(true);
            $table->timestamps();
            $table->unique(['pos_id', 'waktu']);
        });

        // Muka air tanah. Asal: ana_data_mat
        Schema::create('pengamatan_mat', function (Blueprint $table) {
            $table->id();
            $this->kolomUmum($table);
            $table->decimal('muka_air_tanah', 10, 3)->nullable()->comment('m');
            $table->string('keterangan')->nullable();
            $table->boolean('tervalidasi')->default(true);
            $table->timestamps();
            $table->unique(['pos_id', 'waktu']);
        });

        // Prakiraan sifat hujan. Asal: ana_data_hidromet (kosong di data lama)
        Schema::create('prakiraan_hidromet', function (Blueprint $table) {
            $table->id();
            $this->kolomUmum($table);
            $table->decimal('normal_min', 8, 2)->nullable();
            $table->decimal('normal_max', 8, 2)->nullable();
            $table->decimal('prakiraan_min', 8, 2)->nullable();
            $table->decimal('prakiraan_max', 8, 2)->nullable();
            $table->string('sifat', 50)->nullable();
            $table->string('keterangan')->nullable();
            $table->boolean('tervalidasi')->default(true);
            $table->timestamps();
            $table->unique(['pos_id', 'waktu']);
        });

        // Kualitas air. Asal: ana_data_kualitas_air (65 kolom) dipecah menjadi
        // header pengamatan + daftar parameter + nilai per parameter.
        Schema::create('parameter_kualitas_air', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique()->comment('Nama kolom lama tanpa prefix, mis. ph, bod, cod');
            $table->string('nama', 100);
            $table->string('satuan', 30)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('pengamatan_kualitas_air', function (Blueprint $table) {
            $table->id();
            $this->kolomUmum($table);
            $table->string('lokasi')->nullable();
            $table->string('pengukuran_debit')->nullable();
            $table->string('cuaca')->nullable();
            $table->string('hanyutan')->nullable();
            $table->string('sedimen')->nullable();
            $table->string('bau')->nullable();
            $table->string('kotoran_minyak')->nullable();
            $table->string('lumut')->nullable();
            $table->string('vegetasi')->nullable();
            $table->string('keadaan_sungai')->nullable();
            $table->decimal('indeks_pencemaran', 10, 4)->nullable();
            $table->decimal('storet', 10, 2)->nullable();
            $table->string('keterangan')->nullable();
            $table->boolean('tervalidasi')->default(true);
            $table->timestamps();
            $table->index(['pos_id', 'waktu']);
        });

        Schema::create('nilai_kualitas_air', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengamatan_kualitas_air_id')->constrained('pengamatan_kualitas_air')->cascadeOnDelete();
            $table->foreignId('parameter_kualitas_air_id')->constrained('parameter_kualitas_air')->restrictOnDelete();
            $table->decimal('nilai', 16, 4);
            $table->unique(['pengamatan_kualitas_air_id', 'parameter_kualitas_air_id'], 'nilai_kualitas_air_unik');
        });
    }

    private function kolomUmum(Blueprint $table): void
    {
        $table->foreignId('pos_id')->constrained('pos')->restrictOnDelete();
        $table->dateTime('waktu');
        $table->foreignId('diinput_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_kualitas_air');
        Schema::dropIfExists('pengamatan_kualitas_air');
        Schema::dropIfExists('parameter_kualitas_air');
        Schema::dropIfExists('prakiraan_hidromet');
        Schema::dropIfExists('pengamatan_mat');
        Schema::dropIfExists('pengamatan_klimatologi');
        Schema::dropIfExists('pengamatan_debit');
        Schema::dropIfExists('pengamatan_awlr');
        Schema::dropIfExists('pengamatan_arr');
    }
};

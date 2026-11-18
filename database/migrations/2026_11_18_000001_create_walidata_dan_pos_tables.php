<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data induk: instansi pengelola data (walidata) dan pos pengamatan.
 *
 * Asal: ana_ref_walidata, ana_ref_pos (+ 10 salinan ref_pos* yang dibuang).
 * ID lama dipertahankan karena dipakai di path file (file/arsip/{pos_id}/...) dan API eksternal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walidata', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('nama_panjang')->nullable();
            $table->string('website')->nullable();
            $table->string('url_ppid')->nullable();
            $table->string('url_kontak')->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('kontak', 150)->nullable();
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->boolean('setujui_otomatis')->default(false)
                ->comment('Permintaan data disetujui otomatis tanpa verifikasi admin');
            $table->timestamps();
        });

        Schema::create('pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walidata_id')->constrained('walidata')->restrictOnDelete();
            $table->string('kode', 50)->nullable();
            $table->string('nama');
            $table->string('tipe', 30)->comment('App\Enums\TipePos: hidrologi, hidrometeorologi, hidrogeologi');
            $table->string('jenis', 30)->comment('App\Enums\JenisPos: arr, awlr, klimatologi, kualitas_air, mat, agwlr');
            $table->string('metode', 20)->default('manual')->comment('App\Enums\MetodePos: manual, telemetri');
            $table->year('tahun_pembuatan')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('elevasi')->nullable()->comment('meter di atas permukaan laut');
            $table->string('nomor_register', 150)->nullable();

            // Lokasi hidrologis & administratif
            $table->string('wilayah_sungai', 150)->nullable();
            $table->string('das', 150)->nullable();
            $table->string('sungai', 100)->nullable();
            $table->string('anak_sungai', 100)->nullable();
            $table->string('provinsi', 150)->nullable();
            $table->string('kabupaten', 150)->nullable();
            $table->string('kecamatan', 150)->nullable();
            $table->string('desa', 150)->nullable();

            // Petugas pengamat di lapangan
            $table->string('petugas_nama', 150)->nullable();
            $table->string('petugas_nip', 50)->nullable();
            $table->string('petugas_telepon', 100)->nullable();

            // Rentang tahun data tersedia (asal: pos_ketersediaan "2017-2024" + kolom awal/akhir)
            $table->year('data_tersedia_dari')->nullable();
            $table->year('data_tersedia_sampai')->nullable();

            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jenis', 'metode']);
            $table->index('kode');
        });

        // Batas siaga TMA per pos: level 1 (normal) s.d. 6. Asal: ana_ref_batas (kolom batas_1..batas_6).
        Schema::create('batas_siaga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->constrained('pos')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->decimal('nilai', 10, 3)->comment('Status = level tertinggi yang nilainya dilampaui');
            $table->timestamps();

            $table->unique(['pos_id', 'level']);
        });

        // Lengkung debit (rating curve) per pos. Asal: ana_ref_rumus_debit (rumus_debit_1..6).
        Schema::create('rumus_debit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->constrained('pos')->cascadeOnDelete();
            $table->string('rumus', 100)->nullable()->comment('Contoh: Q = a * (H - b)^c');
            $table->json('koefisien');
            $table->date('berlaku_mulai')->nullable();
            $table->timestamps();

            $table->index(['pos_id', 'berlaku_mulai']);
        });

        // Arsip PDF tahunan per pos. Asal: ana_data_arsip.
        Schema::create('arsip_pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->constrained('pos')->cascadeOnDelete();
            $table->year('tahun');
            $table->string('path');
            $table->unsignedBigInteger('ukuran')->nullable()->comment('byte');
            $table->string('status', 50)->nullable();
            $table->timestamps();

            $table->index(['pos_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arsip_pos');
        Schema::dropIfExists('rumus_debit');
        Schema::dropIfExists('batas_siaga');
        Schema::dropIfExists('pos');
        Schema::dropIfExists('walidata');
    }
};

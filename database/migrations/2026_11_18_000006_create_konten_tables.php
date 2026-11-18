<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konten portal publik.
 *
 * Perubahan utama:
 * - Kolom file berulang (album_file1..8, gpeta_file1..5, infografis_file1..5, buletin_img1..3, *_pdf)
 *   dipindah ke tabel polimorfik `lampiran` (satu baris per file).
 * - Kategori teks bebas (buletin_kategori, gpeta_jenis, buku_jenis, kejadian_kategori) menjadi tabel `kategori`.
 * - Kolom sisa CMS lain yang tidak pernah terisi dibuang: opd_id, kerangka_id (tabel ana_ref_opd kosong).
 * - *_terbit 'Ya'/'Tidak' menjadi boolean `terbit`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30)->comment('Pemilik kategori: buletin, peta, buku, kejadian');
            $table->string('nama', 150);
            $table->string('slug', 160);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['jenis', 'slug']);
        });

        // Asal: ana_data_buletin
        Schema::create('buletin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->nullable()->constrained('kategori')->nullOnDelete();
            $table->string('bidang', 100)->nullable()->comment('Hidrologi, Hidrometeorologi, Kejadian, ElNino');
            $table->string('tipe', 20)->default('manual')->comment('manual = diunggah admin, otomatis = ditarik dari sumber luar');
            $table->string('judul');
            $table->text('ringkasan')->nullable();
            $table->longText('isi')->nullable();
            $table->string('sumber')->nullable();
            $table->string('url_sumber')->nullable();
            $table->string('url_gambar_sumber')->nullable()->comment('Asal: buletin_img_ori');
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('terbit')->default(true);
            $table->dateTime('tanggal');
            $table->timestamps();

            $table->index(['terbit', 'tanggal']);
        });

        // Asal: ana_data_album
        Schema::create('album', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 100)->nullable()->comment('Lapangan, Acara');
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->string('url')->nullable();
            $table->string('url_video')->nullable();
            $table->boolean('terbit')->default(true);
            $table->date('tanggal');
            $table->timestamps();
        });

        // Foto pos. Asal: ana_data_galeri
        Schema::create('galeri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->nullable()->constrained('pos')->nullOnDelete();
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->boolean('terbit')->default(true);
            $table->date('tanggal');
            $table->timestamps();
        });

        // Asal: ana_data_glosarium
        Schema::create('glosarium', function (Blueprint $table) {
            $table->id();
            $table->string('bidang', 50)->nullable()->comment('Umum, Hidrologi, Hidrometeorologi, Hidrogeologi');
            $table->string('istilah');
            $table->text('keterangan')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('terbit')->default(true);
            $table->date('tanggal')->nullable();
            $table->timestamps();
        });

        // Asal: ana_data_gpeta
        Schema::create('peta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->nullable()->constrained('kategori')->nullOnDelete();
            $table->foreignId('buletin_id')->nullable()->constrained('buletin')->nullOnDelete();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->string('sumber', 155)->nullable();
            $table->string('url')->nullable();
            $table->boolean('terbit')->default(true);
            $table->date('tanggal')->nullable();
            $table->timestamps();
        });

        // Asal: ana_data_infografis
        Schema::create('infografis', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('keterangan')->nullable();
            $table->string('url')->nullable();
            $table->boolean('terbit')->default(true);
            $table->date('tanggal');
            $table->timestamps();
        });

        // Asal: ana_data_kejadian
        Schema::create('kejadian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_id')->nullable()->constrained('pos')->nullOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('kategori')->nullOnDelete();
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->boolean('terbit')->default(true);
            $table->date('tanggal');
            $table->timestamps();
        });

        // Dokumen/produk hukum. Asal: ana_data_buku
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->nullable()->constrained('kategori')->nullOnDelete();
            $table->string('nama', 155);
            $table->text('isi')->nullable();
            $table->string('tahun', 50)->nullable();
            $table->boolean('terbit')->default(true);
            $table->timestamps();
        });

        // Distribusi air bersih saat kekeringan. Asal: ana_data_pengiriman
        Schema::create('pengiriman_air', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->unsignedInteger('jumlah_terdampak')->nullable()->comment('jiwa');
            $table->unsignedInteger('jumlah_tangki')->nullable();
            $table->unsignedInteger('volume')->nullable()->comment('liter');
            $table->string('penyedia')->nullable();
            $table->string('fasilitas_umum')->nullable();
            $table->string('kontak', 100)->nullable();
            $table->string('catatan')->nullable();
            $table->string('desa')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 50)->nullable();
            $table->timestamps();

            $table->index('tanggal');
        });

        // File milik konten mana pun (buletin, album, galeri, peta, infografis, kejadian, dokumen, pengiriman_air, glosarium).
        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();
            $table->morphs('lampiran');
            $table->string('jenis', 20)->comment('gambar, pdf, dokumen');
            $table->string('path');
            $table->string('path_kecil')->nullable()->comment('Thumbnail');
            $table->string('ekstensi', 20)->nullable();
            $table->unsignedBigInteger('ukuran')->nullable()->comment('byte');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran');
        Schema::dropIfExists('pengiriman_air');
        Schema::dropIfExists('dokumen');
        Schema::dropIfExists('kejadian');
        Schema::dropIfExists('infografis');
        Schema::dropIfExists('peta');
        Schema::dropIfExists('glosarium');
        Schema::dropIfExists('galeri');
        Schema::dropIfExists('album');
        Schema::dropIfExists('buletin');
        Schema::dropIfExists('kategori');
    }
};

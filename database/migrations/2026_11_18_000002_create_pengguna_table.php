<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal: ana_pengguna (ana_pengguna2 & ana_ref_pos555 dibuang: tidak dipakai kode).
 *
 * Password lama berupa MD5 disimpan di `password_md5` dan `password` dibiarkan NULL.
 * Saat login pertama di aplikasi baru: cocokkan md5, simpan bcrypt ke `password`,
 * lalu kosongkan `password_md5`. Lihat Pengguna::cocokkanPasswordLama().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walidata_id')->nullable()->constrained('walidata')->nullOnDelete();
            $table->foreignId('pos_id')->nullable()->constrained('pos')->nullOnDelete()
                ->comment('Diisi untuk peran petugas_pos');
            $table->string('username')->unique();
            $table->string('nama');
            $table->string('email', 100)->nullable()->comment('Tidak unik: data lama hampir semuanya kosong');
            $table->string('telepon', 25)->nullable();
            $table->string('password')->nullable();
            $table->char('password_md5', 32)->nullable()->comment('Warisan sistem lama, dihapus setelah login pertama');
            $table->string('peran', 30)->comment('App\Enums\PeranPengguna: superadmin, admin_walidata, petugas_pos');

            // Penanggung jawab / pengamat lapangan (asal: p_nama, p_telp, p_nik)
            $table->string('pengamat_nama', 150)->nullable();
            $table->string('pengamat_telepon', 150)->nullable();
            $table->string('pengamat_nik', 50)->nullable();

            $table->boolean('aktif')->default(true);
            $table->timestamp('terakhir_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Skema identik dengan laravel/sanctum agar bisa langsung dipakai setelah Sanctum dipasang.
        // Token lama aplikasi Android diimpor sebagai sha256(token) tanpa prefix "id|";
        // Sanctum menerima format itu, jadi aplikasi mobile tidak perlu diubah.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('pengguna');
    }
};

<?php

namespace App\Providers;

use App\Models;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Nilai kolom *_type pada relasi polimorfik (lampiran, personal_access_tokens).
        // Memakai alias agar data tidak bergantung pada nama class PHP.
        Relation::enforceMorphMap([
            'pengguna' => Models\Pengguna::class,
            'buletin' => Models\Buletin::class,
            'album' => Models\Album::class,
            'galeri' => Models\Galeri::class,
            'glosarium' => Models\Glosarium::class,
            'peta' => Models\Peta::class,
            'infografis' => Models\Infografis::class,
            'kejadian' => Models\Kejadian::class,
            'dokumen' => Models\Dokumen::class,
            'pengiriman_air' => Models\PengirimanAir::class,
        ]);

        // Menu Pengaturan (pos, walidata, pengguna, perangkat, ambang batas, rumus debit): route memakai can:superadmin.
        Gate::define('superadmin', fn (Models\Pengguna $pengguna) => $pengguna->isSuperadmin());

        // Grup Ketersediaan data (Unduh Data, File Arsip, Ketersediaan): superadmin dan admin walidata, bukan petugas pos
        // (sama dengan sidebar; petugas tidak boleh memverifikasi permintaan atau mengubah arsip lewat URL langsung).
        Gate::define('admin', fn (Models\Pengguna $pengguna) => $pengguna->peran !== \App\Enums\PeranPengguna::PetugasPos);
    }
}

<?php

namespace App\Providers;

use App\Models;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    }
}

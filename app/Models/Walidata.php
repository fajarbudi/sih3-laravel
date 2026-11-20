<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * Instansi pengelola data (BBWS, Dinas PU, DLHK, Staklim, PJT, dst.).
 */
class Walidata extends Model
{
    protected $table = 'walidata';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        // Menu Walidata portal publik di-cache (lihat AppServiceProvider); segarkan saat data berubah.
        static::saved(fn () => Cache::forget('portal.menu-walidata'));
        static::deleted(fn () => Cache::forget('portal.menu-walidata'));
    }

    protected function casts(): array
    {
        return [
            'setujui_otomatis' => 'boolean',
        ];
    }

    public function pos(): HasMany
    {
        return $this->hasMany(Pos::class);
    }

    public function pengguna(): HasMany
    {
        return $this->hasMany(Pengguna::class);
    }
}

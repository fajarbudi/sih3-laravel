<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instansi pengelola data (BBWS, Dinas PU, DLHK, Staklim, PJT, dst.).
 */
class Walidata extends Model
{
    protected $table = 'walidata';

    protected $guarded = ['id'];

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

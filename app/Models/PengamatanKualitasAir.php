<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengamatanKualitasAir extends Model
{
    protected $table = 'pengamatan_kualitas_air';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'indeks_pencemaran' => 'float',
            'storet' => 'float',
            'tervalidasi' => 'boolean',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(NilaiKualitasAir::class);
    }

    public function parameter(): BelongsToMany
    {
        return $this->belongsToMany(ParameterKualitasAir::class, 'nilai_kualitas_air')
            ->withPivot('nilai');
    }

    /** @return array<string, float> kode parameter => nilai, mis. ['ph' => 7.1, 'bod' => 2.3] */
    public function nilaiPerKode(): array
    {
        return $this->parameter->mapWithKeys(fn ($p) => [$p->kode => (float) $p->pivot->nilai])->all();
    }
}

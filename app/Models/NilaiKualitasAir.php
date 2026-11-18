<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiKualitasAir extends Model
{
    protected $table = 'nilai_kualitas_air';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai' => 'float',
        ];
    }

    public function pengamatan(): BelongsTo
    {
        return $this->belongsTo(PengamatanKualitasAir::class, 'pengamatan_kualitas_air_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ParameterKualitasAir::class, 'parameter_kualitas_air_id');
    }
}

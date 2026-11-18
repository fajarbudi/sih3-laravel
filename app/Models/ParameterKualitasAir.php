<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParameterKualitasAir extends Model
{
    protected $table = 'parameter_kualitas_air';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }
}

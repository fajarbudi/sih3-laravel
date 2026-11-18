<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetriInstrumen extends Model
{
    protected $table = 'telemetri_instrumen';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'diterima_at' => 'datetime',
            'nilai' => 'float',
        ];
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ParameterInstrumen::class, 'parameter_instrumen_id');
    }
}

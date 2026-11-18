<?php

namespace App\Models;

use App\Enums\SumberTelemetri;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetriKlimatologi extends Model
{
    protected $table = 'telemetri_klimatologi';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'diterima_at' => 'datetime',
            'sumber' => SumberTelemetri::class,
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }
}

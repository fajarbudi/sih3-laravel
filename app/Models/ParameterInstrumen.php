<?php

namespace App\Models;

use App\Enums\SumberTelemetri;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kanal sensor instrumen (piezometer, V-notch, crack, debit, katup, ...) dari sistem vendor.
 */
class ParameterInstrumen extends Model
{
    protected $table = 'parameter_instrumen';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sumber' => SumberTelemetri::class,
            'batas_alarm' => 'array',
            'tampil' => 'boolean',
            'aktif' => 'boolean',
            'terakhir_diterima_at' => 'datetime',
            'nilai_terakhir' => 'float',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function bacaan(): HasMany
    {
        return $this->hasMany(TelemetriInstrumen::class);
    }
}

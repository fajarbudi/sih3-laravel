<?php

namespace App\Models;

use App\Enums\SumberTelemetri;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pemetaan logger/perangkat vendor ke pos. Sinkronisasi telemetri mencari pos lewat tabel ini,
 * bukan lewat pos_id yang di-hard-code seperti di controller Api*.php lama.
 */
class PerangkatTelemetri extends Model
{
    protected $table = 'perangkat_telemetri';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sumber' => SumberTelemetri::class,
            'faktor_skala' => 'float',
            'aktif' => 'boolean',
            'terakhir_diterima_at' => 'datetime',
            'nilai_terakhir' => 'float',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    /** Konversi nilai mentah vendor ke satuan baku. */
    public function skalakan(?float $nilaiMentah): ?float
    {
        return $nilaiMentah === null ? null : $nilaiMentah * $this->faktor_skala;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pengajuan permintaan data oleh publik; berisi satu atau lebih pos.
 */
class PermintaanData extends Model
{
    protected $table = 'permintaan_data';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'periode_awal' => 'date',
            'periode_akhir' => 'date',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function item(): HasMany
    {
        return $this->hasMany(PermintaanDataPos::class);
    }

    public function survei(): HasMany
    {
        return $this->hasMany(SurveiKepuasan::class);
    }
}

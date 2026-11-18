<?php

namespace App\Models;

use App\Enums\StatusPermintaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pos dalam permintaan data. Disetujui/ditolak oleh admin walidata pemilik pos.
 */
class PermintaanDataPos extends Model
{
    protected $table = 'permintaan_data_pos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => StatusPermintaan::class,
            'diproses_at' => 'datetime',
        ];
    }

    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanData::class, 'permintaan_data_id');
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diproses_oleh');
    }
}

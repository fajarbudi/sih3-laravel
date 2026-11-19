<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengamatanKlimatologi extends Model
{
    protected $table = 'pengamatan_klimatologi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'tervalidasi' => 'boolean',
            'kelembapan' => 'float',
            'suhu_min' => 'float',
            'suhu_max' => 'float',
            'suhu_rata' => 'float',
            'suhu_air' => 'float',
            'suhu_air_min' => 'float',
            'suhu_air_max' => 'float',
            'penguapan' => 'float',
            'kecepatan_angin' => 'float',
            'radiasi' => 'float',
            'penyinaran' => 'float',
            'hujan' => 'float',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function penginput(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diinput_oleh');
    }
}

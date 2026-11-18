<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveiKepuasan extends Model
{
    protected $table = 'survei_kepuasan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jawaban' => 'array',
        ];
    }

    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(PermintaanData::class, 'permintaan_data_id');
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function walidata(): BelongsTo
    {
        return $this->belongsTo(Walidata::class);
    }
}

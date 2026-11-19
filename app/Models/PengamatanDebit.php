<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengamatanDebit extends Model
{
    protected $table = 'pengamatan_debit';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'tervalidasi' => 'boolean',
            'intake_kanan' => 'float',
            'intake_kiri' => 'float',
            'limpas' => 'float',
            'debit' => 'float',
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

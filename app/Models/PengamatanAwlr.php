<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengamatanAwlr extends Model
{
    protected $table = 'pengamatan_awlr';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
            'tervalidasi' => 'boolean',
            'tma' => 'float',
            'limpas' => 'float',
            'intake_kiri' => 'float',
            'intake_kanan' => 'float',
            'debit_pdam' => 'float',
            'pembilas_kiri' => 'float',
            'pembilas_kanan' => 'float',
            'debit_suplesi' => 'float',
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

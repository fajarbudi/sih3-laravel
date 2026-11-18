<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapArrPerJam extends Model
{
    protected $table = 'rekap_arr_per_jam';

    // Kunci komposit (pos_id, jam): tulis lewat query builder upsert(), bukan save().
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jam' => 'datetime',
            'hujan' => 'float',
            'diperbarui_at' => 'datetime',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }
}

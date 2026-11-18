<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArsipPos extends Model
{
    protected $table = 'arsip_pos';

    protected $guarded = ['id'];

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }
}

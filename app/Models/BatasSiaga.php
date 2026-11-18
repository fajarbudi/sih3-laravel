<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatasSiaga extends Model
{
    protected $table = 'batas_siaga';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai' => 'float',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }
}

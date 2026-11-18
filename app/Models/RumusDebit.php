<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RumusDebit extends Model
{
    protected $table = 'rumus_debit';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'koefisien' => 'array',
            'berlaku_mulai' => 'date',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Kejadian extends Model
{
    protected $table = 'kejadian';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'terbit' => 'boolean',
            'tanggal' => 'date',
        ];
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiran')->orderBy('urutan');
    }
}

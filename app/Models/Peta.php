<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Peta extends Model
{
    protected $table = 'peta';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'terbit' => 'boolean',
            'tanggal' => 'date',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    public function buletin(): BelongsTo
    {
        return $this->belongsTo(Buletin::class);
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiran')->orderBy('urutan');
    }
}

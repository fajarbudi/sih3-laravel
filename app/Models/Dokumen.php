<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Dokumen extends Model
{
    protected $table = 'dokumen';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'terbit' => 'boolean',
        ];
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

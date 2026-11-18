<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Glosarium extends Model
{
    protected $table = 'glosarium';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'terbit' => 'boolean',
            'tanggal' => 'date',
        ];
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiran')->orderBy('urutan');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Lampiran extends Model
{
    protected $table = 'lampiran';

    protected $guarded = ['id'];

    public function lampiran(): MorphTo
    {
        return $this->morphTo();
    }

    /** Alamat publik berkas (disk public lewat storage:link). */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** Alamat versi kecil (thumbnail) bila ada, selain itu berkas aslinya. */
    public function urlKecil(): string
    {
        return Storage::disk('public')->url($this->path_kecil ?: $this->path);
    }
}

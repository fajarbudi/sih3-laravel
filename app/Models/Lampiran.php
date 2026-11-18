<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Lampiran extends Model
{
    protected $table = 'lampiran';

    protected $guarded = ['id'];

    public function lampiran(): MorphTo
    {
        return $this->morphTo();
    }
}

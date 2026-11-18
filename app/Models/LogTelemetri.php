<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogTelemetri extends Model
{
    protected $table = 'log_telemetri';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}

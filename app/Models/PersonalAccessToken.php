<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Pengganti sementara Laravel\Sanctum\PersonalAccessToken (skema tabel sama).
 * Setelah `composer require laravel/sanctum`, model ini bisa dihapus dan
 * Pengguna memakai trait HasApiTokens.
 */
class PersonalAccessToken extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable');
    }

    /** Cari token dari header Bearer (format lama tanpa "id|" maupun format Sanctum). */
    public static function findToken(string $token): ?self
    {
        if (str_contains($token, '|')) {
            [$id, $token] = explode('|', $token, 2);

            $instance = static::find($id);

            return $instance && hash_equals($instance->token, hash('sha256', $token)) ? $instance : null;
        }

        return static::where('token', hash('sha256', $token))->first();
    }
}

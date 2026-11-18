<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'password_md5', 'remember_token'])]
class Pengguna extends Authenticatable
{
    use Notifiable;

    protected $table = 'pengguna';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'peran' => PeranPengguna::class,
            'aktif' => 'boolean',
            'terakhir_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function walidata(): BelongsTo
    {
        return $this->belongsTo(Walidata::class);
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(Pos::class);
    }

    /** Token API (aplikasi Android). Skema kompatibel dengan laravel/sanctum. */
    public function tokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    public function isSuperadmin(): bool
    {
        return $this->peran === PeranPengguna::Superadmin;
    }

    /**
     * Verifikasi password akun yang belum pernah login sejak migrasi dari sistem lama (MD5).
     * Jika cocok, password langsung di-hash ulang dengan bcrypt dan MD5 dihapus.
     * Dipanggil dari alur login bila Hash::check gagal / password masih null.
     */
    public function cocokkanPasswordLama(string $password): bool
    {
        if ($this->password_md5 === null || ! hash_equals($this->password_md5, md5($password))) {
            return false;
        }

        $this->forceFill(['password' => $password, 'password_md5' => null])->save();

        return true;
    }
}

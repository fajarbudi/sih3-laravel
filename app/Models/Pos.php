<?php

namespace App\Models;

use App\Enums\JenisPos;
use App\Enums\MetodePos;
use App\Enums\PeranPengguna;
use App\Enums\TipePos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pos pengamatan hidrologi / hidrometeorologi / hidrogeologi.
 */
class Pos extends Model
{
    use SoftDeletes;

    protected $table = 'pos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipe' => TipePos::class,
            'jenis' => JenisPos::class,
            'metode' => MetodePos::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'aktif' => 'boolean',
        ];
    }

    public function walidata(): BelongsTo
    {
        return $this->belongsTo(Walidata::class);
    }

    public function perangkatTelemetri(): HasMany
    {
        return $this->hasMany(PerangkatTelemetri::class);
    }

    public function batasSiaga(): HasMany
    {
        return $this->hasMany(BatasSiaga::class)->orderBy('level');
    }

    public function rumusDebit(): HasMany
    {
        return $this->hasMany(RumusDebit::class)->latest('berlaku_mulai');
    }

    public function arsip(): HasMany
    {
        return $this->hasMany(ArsipPos::class);
    }

    public function petugas(): HasMany
    {
        return $this->hasMany(Pengguna::class);
    }

    public function scopeJenis(Builder $query, JenisPos $jenis): void
    {
        $query->where('jenis', $jenis);
    }

    public function scopeTelemetri(Builder $query): void
    {
        $query->where('metode', MetodePos::Telemetri);
    }

    /** Pos yang boleh dilihat pengguna: superadmin semua, admin walidata miliknya, petugas posnya sendiri. */
    public function scopeTerlihatOleh(Builder $query, Pengguna $pengguna): void
    {
        match ($pengguna->peran) {
            PeranPengguna::Superadmin => null,
            PeranPengguna::AdminWalidata => $query->where('walidata_id', $pengguna->walidata_id),
            PeranPengguna::PetugasPos => $query->whereKey($pengguna->pos_id),
        };
    }

    /**
     * Level siaga untuk nilai TMA tertentu: level tertinggi yang ambangnya dilampaui (minimal 1).
     * Logika sama dengan Gets.php lama, tetapi berbasis tabel batas_siaga.
     */
    public function levelSiaga(float $nilai): int
    {
        return $this->batasSiaga
            ->filter(fn (BatasSiaga $batas) => $batas->level > 1 && $nilai > $batas->nilai)
            ->max('level') ?? 1;
    }
}

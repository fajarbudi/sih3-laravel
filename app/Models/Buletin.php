<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Buletin extends Model
{
    protected $table = 'buletin';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'terbit' => 'boolean',
            'tanggal' => 'datetime',
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

    /**
     * Hapus buletin otomatis Staklim Jateng yang tersimpan berulang kali.
     * Penyebabnya bug Scrap.php lama: cek duplikat membandingkan URL gambar BMKG dengan path file lokal,
     * sehingga buletin yang sama disimpan ulang setiap hari.
     * Per gambar disisakan satu: yang sudah terbit, kalau tidak ada yang paling awal.
     */
    public static function hapusDuplikatOtomatis(): int
    {
        $hapus = []; // id yang dihapus => id yang disisakan
        static::where('tipe', 'otomatis')
            ->where('url_sumber', 'like', '%staklim-jateng%')
            ->whereNotNull('url_gambar_sumber')
            ->orderByDesc('terbit')->orderBy('id')
            ->get(['id', 'url_gambar_sumber'])
            ->groupBy('url_gambar_sumber')
            ->each(function ($grup) use (&$hapus) {
                foreach ($grup->slice(1) as $duplikat) {
                    $hapus[$duplikat->id] = $grup->first()->id;
                }
            });

        DB::transaction(function () use ($hapus) {
            foreach ($hapus as $lama => $baru) {
                Peta::where('buletin_id', $lama)->update(['buletin_id' => $baru]);
            }
            foreach (array_chunk(array_keys($hapus), 500) as $id) {
                Lampiran::where('lampiran_type', 'buletin')->whereIn('lampiran_id', $id)->delete();
                static::whereIn('id', $id)->delete();
            }
        });

        return count($hapus);
    }
}

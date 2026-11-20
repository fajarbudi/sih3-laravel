<?php

namespace App\Services;

use App\Enums\JenisPos;
use App\Enums\MetodePos;
use App\Models\Pos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bacaan terakhir setiap pos untuk portal publik:
 * - pos telemetri: bacaan terbaru di tabel telemetri_* (sumber yang paling baru mengirim, x faktor_skala perangkat).
 *   Sengaja tidak memakai kolom perangkat_telemetri.terakhir_diterima_at/nilai_terakhir: kolom itu hanya diperbarui
 *   telemetri:fetch, sehingga bisa tertinggal dari data yang sudah ada (mis. setelah impor);
 * - pos manual: pengamatan terakhir di tabel sesuai jenis pos.
 * Status: "online" bila telemetri mengirim dalam 24 jam terakhir, "offline" bila lebih lama, "manual" untuk pos manual.
 */
class NilaiTerakhirPos
{
    /** Batas data telemetri dianggap masih mengirim. */
    public const BATAS_ONLINE_JAM = 24;

    /** Jenis pos => [tabel pengamatan manual, kolom nilai, label, satuan]. */
    private const MANUAL = [
        'awlr' => ['pengamatan_awlr', 'tma', 'Tinggi muka air', 'm'],
        'arr' => ['pengamatan_arr', 'hujan', 'Curah hujan', 'mm'],
        'klimatologi' => ['pengamatan_klimatologi', 'suhu_rata', 'Suhu rata-rata', '°C'],
        'mat' => ['pengamatan_mat', 'muka_air_tanah', 'Muka air tanah', 'm'],
        'kualitas_air' => ['pengamatan_kualitas_air', 'indeks_pencemaran', 'Indeks pencemaran', ''],
    ];

    /** Jenis pos telemetri => [tabel telemetri, kolom nilai, label, satuan]. */
    private const TELEMETRI = [
        'awlr' => ['telemetri_awlr', 'tma', 'Tinggi muka air', 'm'],
        'agwlr' => ['telemetri_awlr', 'tma', 'Muka air tanah', 'm'],
        'arr' => ['telemetri_arr', 'hujan', 'Curah hujan', 'mm'],
        'klimatologi' => ['telemetri_klimatologi', 'suhu', 'Suhu', '°C'],
    ];

    /**
     * @param  Collection<int, Pos>  $pos  dengan relasi perangkatTelemetri dan batasSiaga
     * @return array<int, array{label: string, nilai: ?float, satuan: string, waktu: ?Carbon, status: string, siaga: int}>
     */
    public function untuk(Collection $pos): array
    {
        $zona = config('services.telemetri.zona_waktu');
        $manual = $this->terakhir($pos->filter(fn (Pos $p) => $p->metode === MetodePos::Manual), self::MANUAL);
        $telemetri = $this->terakhir($pos->filter(fn (Pos $p) => $p->metode === MetodePos::Telemetri), self::TELEMETRI, true);
        $hasil = [];

        foreach ($pos as $p) {
            $jenis = $p->jenis->value;
            if ($p->metode === MetodePos::Telemetri) {
                [, , $label, $satuan] = self::TELEMETRI[$jenis] ?? [null, null, 'Bacaan terakhir', ''];
                $baris = $telemetri[$p->id] ?? null;
                $waktu = $baris ? Carbon::parse($baris->waktu, $zona) : null;
                $faktor = $baris ? ($p->perangkatTelemetri->firstWhere('sumber.value', $baris->sumber)?->faktor_skala ?? 1) : 1;
                $nilai = $baris && $baris->nilai !== null ? (float) $baris->nilai * $faktor : null;
                $status = $waktu && $waktu->gte(now($zona)->subHours(self::BATAS_ONLINE_JAM)) ? 'online' : 'offline';
            } else {
                [, , $label, $satuan] = self::MANUAL[$jenis] ?? [null, null, 'Pengamatan terakhir', ''];
                $baris = $manual[$p->id] ?? null;
                $waktu = $baris ? Carbon::parse($baris->waktu) : null;
                $nilai = $baris && $baris->nilai !== null ? (float) $baris->nilai : null;
                $status = 'manual';
            }

            $hasil[$p->id] = [
                'label' => $label,
                'nilai' => $nilai,
                'satuan' => $satuan,
                'waktu' => $waktu,
                'status' => $status,
                // Status siaga hanya untuk muka air yang punya ambang batas (lihat Pos::levelSiaga); 1 = normal.
                'siaga' => $jenis === JenisPos::Awlr->value && $nilai !== null && $p->batasSiaga->isNotEmpty() ? $p->levelSiaga($nilai) : 1,
            ];
        }

        return $hasil;
    }

    /**
     * Bacaan terakhir per pos, satu kueri per jenis. Untuk telemetri (beberapa sumber per pos) diambil yang paling baru.
     *
     * @param  array<string, array{0: string, 1: string}>  $peta  jenis => [tabel, kolom nilai, ...]
     * @return array<int, object{pos_id: int, waktu: string, nilai: mixed, sumber?: string}>
     */
    private function terakhir(Collection $pos, array $peta, bool $telemetri = false): array
    {
        $hasil = [];
        foreach ($pos->groupBy(fn (Pos $p) => $p->jenis->value) as $jenis => $kelompok) {
            if (! isset($peta[$jenis])) {
                continue;
            }
            [$tabel, $kolom] = $peta[$jenis];
            $kunci = $telemetri ? ['pos_id', 'sumber'] : ['pos_id'];
            $terakhir = DB::table($tabel)->whereIn('pos_id', $kelompok->pluck('id'))
                ->select($kunci)->selectRaw('max(waktu) as waktu')->groupBy($kunci);

            DB::table($tabel.' as t')
                ->joinSub($terakhir, 'm', function ($j) use ($kunci) {
                    foreach ([...$kunci, 'waktu'] as $k) {
                        $j->on('m.'.$k, '=', 't.'.$k);
                    }
                })
                ->orderBy('t.waktu')
                ->get(array_merge(['t.pos_id', 't.waktu', 't.'.$kolom.' as nilai'], $telemetri ? ['t.sumber'] : []))
                ->each(function ($b) use (&$hasil) {
                    $hasil[$b->pos_id] = $b; // urut waktu naik: yang terakhir ditulis = paling baru
                });
        }

        return $hasil;
    }
}

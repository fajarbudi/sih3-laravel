<?php

namespace App\Services\Telemetri;

use App\Enums\JenisPos;
use App\Enums\MetodePos;
use App\Enums\SumberTelemetri;
use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ARR & AWLR Perum Jasa Tirta I dari datatirta.jasatirta1.co.id (dulu Apipjt.php).
 * GET /access/{arr|awlr}/latest -> [{access: {lat, long}, data: {nama_station, datetime, ...}}]
 * Stasiun dicocokkan lewat nama; stasiun baru dibuatkan pos. TMA dalam meter (mis. elevasi Waduk Wadaslintang
 * ~167 m), ditampilkan apa adanya seperti halaman admin lama Awlrpjt.php; faktor_skala 1.
 *
 * Token login disimpan di cache dan diperbarui otomatis saat ditolak (401).
 * Di aplikasi lama token diperbarui manual, sehingga penarikan berhenti diam-diam saat token kedaluwarsa.
 */
class FetchDataPjt extends FetchData
{
    protected function sumber(): SumberTelemetri
    {
        return SumberTelemetri::Pjt;
    }

    private Collection $daftar;

    public function fetch(?CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        $this->daftar = $this->perangkat();
        $jumlah = 0;

        foreach ([JenisPos::Arr, JenisPos::Awlr] as $jenis) {
            $respons = $this->ambil("access/{$jenis->value}/latest");
            if ($respons->failed()) {
                $this->catatGagal(null, "{$jenis->value}/latest HTTP {$respons->status()}");

                continue;
            }

            foreach ($respons->json() ?? [] as $stasiun) {
                $data = $stasiun['data'] ?? [];
                $nilai = $jenis === JenisPos::Arr ? ($data['hourly_rf'] ?? null) : ($data['tma'] ?? null);
                if (empty($data['nama_station']) || empty($data['datetime']) || ! is_numeric($nilai)) {
                    continue;
                }

                $baris = $jenis === JenisPos::Arr
                    ? ['waktu' => $data['datetime'], 'hujan' => $nilai, 'hujan_kumulatif' => $data['cumulative'] ?? null]
                    : ['waktu' => $data['datetime'], 'tma' => $nilai, 'level' => $data['level'] ?? null];

                $p = $this->perangkatStasiun($jenis, trim($data['nama_station']), $stasiun['access'] ?? []);
                $jumlah += $this->simpan($p, "telemetri_{$jenis->value}", [$baris]);
            }
        }

        return $jumlah;
    }

    private function ambil(string $path): Response
    {
        $respons = Http::withToken($this->token())->timeout(60)->get(config('services.pjt.url')."/{$path}");

        if ($respons->status() === 401) {
            Cache::forget('telemetri.pjt.token');
            $respons = Http::withToken($this->token())->timeout(60)->get(config('services.pjt.url')."/{$path}");
        }

        return $respons;
    }

    private function token(): string
    {
        return Cache::remember('telemetri.pjt.token', now()->addHours(6), function () {
            $token = Http::asForm()->timeout(60)->post(config('services.pjt.url').'/login', [
                'email' => config('services.pjt.email'),
                'password' => config('services.pjt.password'),
            ])->json('token');

            return $token ?: throw new RuntimeException('Login PJT gagal');
        });
    }

    /** Nama stasiun bisa sama untuk ARR dan AWLR, jadi dicari per jenis pos. */
    private function perangkatStasiun(JenisPos $jenis, string $nama, array $akses): PerangkatTelemetri
    {
        $p = $this->daftar->first(fn ($p) => $p->kode_perangkat === $nama && $p->pos?->jenis === $jenis);
        if ($p) {
            return $p;
        }

        $koordinat = fn ($v) => is_numeric($v = str_replace(',', '.', (string) $v)) ? $v : null;
        $pos = Pos::create([
            'walidata_id' => config('services.pjt.walidata_id'),
            'nama' => $nama,
            'tipe' => $jenis->tipe(),
            'jenis' => $jenis,
            'metode' => MetodePos::Telemetri,
            'latitude' => $koordinat($akses['lat'] ?? null),
            'longitude' => $koordinat($akses['long'] ?? null),
        ]);

        $p = PerangkatTelemetri::create([
            'pos_id' => $pos->id,
            'sumber' => SumberTelemetri::Pjt,
            'kode_perangkat' => $nama,
            'faktor_skala' => 1,
        ])->setRelation('pos', $pos);
        $this->daftar->push($p);

        return $p;
    }
}

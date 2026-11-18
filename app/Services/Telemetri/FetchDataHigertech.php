<?php

namespace App\Services\Telemetri;

use App\Enums\SumberTelemetri;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * ARR & AWLR Kranggan dari api.higertech.com (dulu Apihigertech.php).
 * GET /reading/device/{id}?from=Y-m-d&to=Y-m-d -> {response: [{reading_at, rainfall, water_level}]}
 * Satu alat dipetakan ke dua pos: rainfall ke pos ARR, water_level ke pos AWLR.
 */
class FetchDataHigertech extends FetchData
{
    protected function sumber(): SumberTelemetri
    {
        return SumberTelemetri::Higertech;
    }

    public function fetch(?CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        $sampai ??= now($this->zona());
        $jumlah = 0;

        foreach ($this->perangkat() as $p) {
            [$tabel, $kolom, $kolomApi] = $p->pos->jenis->value === 'arr'
                ? ['telemetri_arr', 'hujan', 'rainfall']
                : ['telemetri_awlr', 'tma', 'water_level'];

            // Mulai dari tanggal data terakhir, agar bacaan menjelang tengah malam ikut terambil.
            $mulai = $dari ?? $this->waktuTerakhir($p, $tabel) ?? $sampai;

            foreach ($this->potongRentang($mulai->copy()->startOfDay(), $sampai, 31) as [$a, $b]) {
                $respons = Http::timeout(60)->get(config('services.higertech.url')."/reading/device/{$p->kode_perangkat}", [
                    'from' => $a->toDateString(),
                    'to' => $b->toDateString(),
                ]);
                if ($respons->failed()) {
                    $this->catatGagal($p->kode_perangkat, "HTTP {$respons->status()}");

                    continue;
                }

                $baris = [];
                foreach ($respons->json('response') ?? [] as $r) {
                    if (is_numeric($r[$kolomApi] ?? null)) {
                        $baris[] = ['waktu' => $r['reading_at'], $kolom => $r[$kolomApi]];
                    }
                }
                $jumlah += $this->simpan($p, $tabel, $baris);
            }
        }

        return $jumlah;
    }
}

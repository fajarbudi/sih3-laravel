<?php

namespace App\Services\Telemetri;

use App\Enums\SumberTelemetri;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * Muka air tanah JIAT Playen, ATAB Prambanan & Mulo dari mini-stesy.monitoring4system.com (dulu Apistesy.php).
 * Hanya nilai terakhir: {data: [{id_logger, waktu, data: [{nama_parameter, nilai}]}]}
 */
class FetchDataStesy extends FetchData
{
    protected function sumber(): SumberTelemetri
    {
        return SumberTelemetri::Stesy;
    }

    public function fetch(?CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        $respons = Http::withHeaders(['Authorization' => (string) config('services.stesy.authorization')])
            ->timeout(60)
            ->get(config('services.stesy.url').'/integrasi/all_logger');
        if ($respons->failed()) {
            $this->catatGagal(null, "HTTP {$respons->status()}");

            return 0;
        }

        $perangkat = $this->perangkat()->keyBy('kode_perangkat');
        $jumlah = 0;

        foreach ($respons->json('data') ?? [] as $logger) {
            $p = $perangkat->get((string) ($logger['id_logger'] ?? ''));
            $mat = collect($logger['data'] ?? [])
                ->first(fn ($d) => in_array($d['nama_parameter'] ?? '', ['Muka_Air_Tanah', 'Muka Air Tanah']));

            if ($p && is_numeric($mat['nilai'] ?? null) && ! empty($logger['waktu'])) {
                $jumlah += $this->simpan($p, 'telemetri_awlr', [['waktu' => $logger['waktu'], 'tma' => $mat['nilai']]]);
            }
        }

        return $jumlah;
    }
}

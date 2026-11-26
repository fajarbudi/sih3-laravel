<?php

namespace App\Services\Telemetri;

use App\Enums\SumberTelemetri;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * AWLR Ngrancah & Bengkok dari log.pcbjogja.com (dulu Apilogin.php).
 * API publik, hanya memberi nilai terakhir: [{id, last_online, status: {water_level}}]
 */
class FetchDataPcbJogja extends FetchData
{
    protected function sumber(): SumberTelemetri
    {
        return SumberTelemetri::PcbJogja;
    }

    public function fetch(?CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        $respons = Http::timeout(60)->get(config('services.pcb_jogja.url'), ['id' => '']);
        if ($respons->failed()) {
            $this->catatGagal(null, "HTTP {$respons->status()}");

            return 0;
        }

        $perangkat = $this->perangkat()->keyBy('kode_perangkat');
        $jumlah = 0;

        foreach ($respons->json() ?? [] as $logger) {
            $p = $perangkat->get($logger['id'] ?? null);
            $tma = $logger['status']['water_level'] ?? null;

            if ($p && is_numeric($tma) && ! empty($logger['last_online'])) {
                $jumlah += $this->simpan($p, 'telemetri_awlr', [['waktu' => $logger['last_online'], 'tma' => $tma]]);
            }
        }

        return $jumlah;
    }
}

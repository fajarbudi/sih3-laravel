<?php

namespace App\Services\Telemetri;

use App\Enums\SumberTelemetri;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * Logger Beacon (ARR & AWLR BBWS) dari API integrasi monitoring4system (HTTP Basic Auth, akun admin).
 * Dokumentasi: https://bbwsso.monitoring4system.com/integrasi_api/docs
 *
 * GET /range_tanggal?id_logger=&awal=&akhir=&interval=jam -> {status, data: [{waktu, data: [{nama_parameter, nilai}]}]}
 * Per jam: hujan dijumlah, TMA dirata-rata oleh API. Jam yang sedang berjalan masih berubah, jadi ditimpa (upsert).
 */
class FetchDataBeacon extends FetchData
{
    /**
     * Nama parameter tidak terdokumentasi lengkap, jadi dicocokkan dengan pola. Logger ARR lama mengirim
     * "Precipitation_Intensity" (bukan "Curah_Hujan"); nilainya per bacaan dan dijumlah API seperti Curah_Hujan.
     */
    private const JENIS = [
        'arr' => ['telemetri_arr', 'hujan', '/hujan|rain|precipitation/i'],
        'awlr' => ['telemetri_awlr', 'tma', '/muka.?air|tma|water.?level/i'],
    ];

    protected function sumber(): SumberTelemetri
    {
        return SumberTelemetri::Beacon;
    }

    public function fetch(?CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        $sampai ??= now($this->zona());
        $jumlah = 0;

        foreach ($this->perangkat() as $p) {
            if (! isset(self::JENIS[$p->pos?->jenis?->value])) {
                continue;
            }
            [$tabel, $kolom, $pola] = self::JENIS[$p->pos->jenis->value];
            $mulai = $dari ?? ($this->waktuTerakhir($p, $tabel) ?? $sampai)->max(now($this->zona())->subDays(7));

            foreach ($this->potongRentang($mulai->copy()->startOfDay(), $sampai, 31) as [$a, $b]) {
                $respons = $this->coba($p->kode_perangkat, fn () => Http::withBasicAuth((string) config('services.beacon.username'), (string) config('services.beacon.password'))
                    ->timeout(90)
                    ->get(config('services.beacon.url').'/range_tanggal', [
                        'id_logger' => $p->kode_perangkat,
                        'awal' => $a->toDateString(),
                        'akhir' => $b->toDateString(),
                        'interval' => 'jam',
                    ]));
                if (! $respons) {
                    continue;
                }
                if ($respons->json('status') !== true) {
                    $this->catatGagal($p->kode_perangkat, $respons->json('pesan') ?? "HTTP {$respons->status()}");

                    continue;
                }

                $baris = [];
                foreach ($respons->json('data') ?? [] as $titik) {
                    $parameter = collect($titik['data'] ?? [])->first(fn ($d) => preg_match($pola, $d['nama_parameter'] ?? ''));
                    if (is_numeric($parameter['nilai'] ?? null)) {
                        $baris[] = ['waktu' => $titik['waktu'], $kolom => $parameter['nilai']];
                    }
                }
                $jumlah += $this->simpan($p, $tabel, $baris, perbarui: true);
            }
        }

        return $jumlah;
    }
}

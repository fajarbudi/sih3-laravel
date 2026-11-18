<?php

namespace App\Services\Telemetri;

use App\Enums\SumberTelemetri;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

/**
 * Logger Tatonas 4101-4106 (dulu Apitatonas.php). Memakai endpoint history agar semua bacaan masuk:
 * GET /history?uc=&pc=&hw=&start_date=&end_date= -> {statusCode, data: {detail: [{tanggal, ...kolom}]}}
 * Rentang maksimal 31 hari. Waktu sudah WIB.
 */
class FetchDataTatonas extends FetchData
{
    /** Kolom history Tatonas -> kolom tabel, per jenis pos. */
    private const KOLOM = [
        'arr' => ['rainfall' => 'hujan', 'curahhujan' => 'hujan'],
        'awlr' => ['waterlevel' => 'tma', 'debitair' => 'debit'],
        'klimatologi' => [
            'windvelocity' => 'kecepatan_angin', 'winddirection' => 'arah_angin', 'radiation' => 'radiasi',
            'airpressure' => 'tekanan', 'airhumidity' => 'kelembapan', 'airtemperatur' => 'suhu',
            'uv' => 'uv', 'panlevel' => 'penguapan', 'rainfall' => 'hujan',
        ],
    ];

    protected function sumber(): SumberTelemetri
    {
        return SumberTelemetri::Tatonas;
    }

    public function fetch(?CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        // endOfDay: logger 4105 memberi label akhir interval (mis. 02:00 saat jam masih 01:30).
        $sampai = ($sampai ?? now($this->zona()))->copy()->endOfDay();
        $jumlah = 0;

        foreach ($this->perangkat() as $p) {
            $jenis = $p->pos->jenis->value;
            $tabel = "telemetri_{$jenis}";
            // Ulang 3 jam terakhir untuk data yang terlambat; logger yang lama mati tidak ditarik lebih dari 7 hari ke belakang.
            $terakhir = $this->waktuTerakhir($p, $tabel);
            $mulai = $dari ?? max(
                $terakhir ? $terakhir->copy()->subHours(3) : now($this->zona())->subDay(),
                now($this->zona())->subDays(7),
            );

            foreach ($this->potongRentang($mulai, $sampai, 30) as [$a, $b]) {
                $respons = Http::timeout(90)->get(config('services.tatonas.url').'/history', [
                    'uc' => config('services.tatonas.uc'),
                    'pc' => config('services.tatonas.pc'),
                    'hw' => $p->kode_perangkat,
                    // start_date eksklusif di API Tatonas; mundur 1 detik agar bacaan tepat di awal rentang ikut.
                    'start_date' => $a->copy()->subSecond()->format('Y-m-d H:i:s'),
                    'end_date' => $b->format('Y-m-d H:i:s'),
                ]);
                if ($respons->json('statusCode') != 200) {
                    $this->catatGagal($p->kode_perangkat, $respons->json('message') ?? "HTTP {$respons->status()}");

                    continue;
                }

                $baris = [];
                foreach ($respons->json('data.detail') ?? [] as $d) {
                    // Semua baris dalam satu insert harus punya kolom yang sama.
                    $nilai = array_fill_keys(array_unique(self::KOLOM[$jenis]), null);
                    foreach (self::KOLOM[$jenis] as $asal => $tujuan) {
                        if (is_numeric($d[$asal] ?? null)) {
                            $nilai[$tujuan] = $d[$asal];
                        }
                    }
                    if (array_filter($nilai, 'is_numeric')) {
                        $baris[] = ['waktu' => $d['tanggal']] + $nilai;
                    }
                }
                $jumlah += $this->simpan($p, $tabel, $baris);
            }
        }

        return $jumlah;
    }
}

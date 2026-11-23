<?php

namespace App\Services\Telemetri;

use App\Enums\JenisPos;
use App\Enums\MetodePos;
use App\Enums\SumberTelemetri;
use App\Models\ParameterInstrumen;
use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SCADASH Bendungan Sermo (dulu cron aplikasi hidrologi: sermo_tma, sermo_vnotch, sermo_instrument).
 * Dokumentasi: http://202.180.30.82/public/API/v1/
 *
 * Setiap tarikan:
 * 1. get-lastvalue  -> daftarkan perangkat baru: TMA ke pos AWLR, klimatologi ke pos klimatologi,
 *                      sisanya (V-notch, piezometer, crack, debit, ...) ke parameter_instrumen. Genset dilewati.
 * 2. get-rangevalues per perangkat (waktu UTC, maks 31 hari) -> simpan satu bacaan per 5 menit.
 */
class FetchDataSermo extends FetchData
{
    private const KLIMATOLOGI = [
        'kecepatan_angin' => 'kecepatan_angin', 'arah_angin' => 'arah_angin', 'temperature_udara' => 'suhu',
        'kelembapan' => 'kelembapan', 'tekanan_udara' => 'tekanan', 'radiasi' => 'radiasi', 'curah_hujan' => 'hujan',
    ];

    /** Sebagian logger mengirim tiap 5-20 detik; cukup satu bacaan per 5 menit. */
    private const SLOT_DETIK = 300;

    protected function sumber(): SumberTelemetri
    {
        return SumberTelemetri::Sermo;
    }

    public function fetch(?CarbonInterface $dari, ?CarbonInterface $sampai): int
    {
        $sampai ??= now();
        // Gagal mengambil daftar perangkat tidak menghentikan penarikan data perangkat yang sudah terdaftar.
        try {
            $this->daftarkanPerangkat($this->api('get-lastvalue.php'));
        } catch (RuntimeException $e) {
            $this->catatGagal(null, "get-lastvalue: {$e->getMessage()}");
        }

        $perangkat = $this->perangkat();
        $awlr = $perangkat->whereNotNull('sensor')->groupBy('kode_perangkat');
        $klimatologi = $perangkat->whereNull('sensor')->keyBy('kode_perangkat');
        $instrumen = ParameterInstrumen::where('sumber', $this->sumber())->where('aktif', true)->get()->groupBy('kode_perangkat');

        $jumlah = 0;
        foreach ($awlr->keys()->merge($klimatologi->keys())->merge($instrumen->keys())->unique() as $kode) {
            $tmaP = ($awlr[$kode] ?? collect())->keyBy('sensor');
            $klimP = $klimatologi[$kode] ?? null;
            $instP = ($instrumen[$kode] ?? collect())->keyBy('kode_parameter');
            $parameter = [...$tmaP->keys(), ...($klimP ? array_keys(self::KLIMATOLOGI) : []), ...$instP->keys()];
            $mulai = $dari ?? $this->mulai($tmaP, $klimP, $instP);

            // Mulai dari awal jam agar pilihan bacaan per slot 5 menit sama setiap kali ditarik ulang.
            foreach ($this->potongRentang($mulai->copy()->startOfHour(), $sampai, 31) as [$a, $b]) {
                try {
                    $respons = $this->api('get-rangevalues.php', [
                        'device' => $kode,
                        'params' => implode(',', $parameter),
                        'start' => $a->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                        'end' => $b->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    ]);
                } catch (RuntimeException $e) {
                    $this->catatGagal($kode, $e->getMessage());

                    continue;
                }

                $nilai = []; // parameter => [waktu WIB => nilai]
                foreach ($respons as $r) {
                    foreach ($r['params'] ?? [] as $param => $isi) {
                        $nilai[$param] = $this->sampel($isi['data'] ?? []);
                    }
                }

                foreach ($tmaP as $sensor => $p) {
                    $jumlah += $this->simpan($p, 'telemetri_awlr', $this->keBaris($nilai[$sensor] ?? [], 'tma'));
                }
                if ($klimP) {
                    $jumlah += $this->simpanKlimatologi($klimP, $nilai);
                }
                foreach ($instP as $param => $pi) {
                    $jumlah += $this->simpanInstrumen($pi, $nilai[$param] ?? []);
                }
            }
        }

        return $jumlah;
    }

    private function simpanKlimatologi(PerangkatTelemetri $p, array $nilai): int
    {
        $perWaktu = [];
        foreach (self::KLIMATOLOGI as $param => $kolom) {
            foreach ($nilai[$param] ?? [] as $waktu => $v) {
                $perWaktu[$waktu][$kolom] = $v;
            }
        }

        $kosong = array_fill_keys(self::KLIMATOLOGI, null);
        $baris = array_map(fn ($waktu) => ['waktu' => $waktu] + $perWaktu[$waktu] + $kosong, array_keys($perWaktu));

        return $this->simpan($p, 'telemetri_klimatologi', $baris, perbarui: true);
    }

    private function simpanInstrumen(ParameterInstrumen $p, array $nilai): int
    {
        if (! $nilai) {
            return 0;
        }

        $jumlah = 0;
        foreach (array_chunk($nilai, 500, true) as $potongan) {
            $jumlah += DB::table('telemetri_instrumen')->insertOrIgnore(array_map(
                fn ($waktu, $v) => ['parameter_instrumen_id' => $p->id, 'waktu' => $waktu, 'nilai' => $v, 'diterima_at' => now()],
                array_keys($potongan), $potongan,
            ));
        }
        $p->update(['terakhir_diterima_at' => array_key_last($nilai), 'nilai_terakhir' => end($nilai)]);

        return $jumlah;
    }

    /** Lanjut dari bacaan terakhir yang tersimpan (diulang 1 jam), paling jauh 7 hari ke belakang. */
    private function mulai(Collection $tmaP, ?PerangkatTelemetri $klimP, Collection $instP): CarbonInterface
    {
        $terakhirInstrumen = $instP->isEmpty() ? null
            : DB::table('telemetri_instrumen')->whereIn('parameter_instrumen_id', $instP->pluck('id'))->max('waktu');

        $terakhir = collect([
            ...$tmaP->map(fn ($p) => $this->waktuTerakhir($p, 'telemetri_awlr')),
            $klimP ? $this->waktuTerakhir($klimP, 'telemetri_klimatologi') : null,
            $terakhirInstrumen ? Carbon::parse($terakhirInstrumen, $this->zona()) : null,
        ])->filter()->max();

        return ($terakhir ? $terakhir->copy()->subHour() : now()->subHours(3))->max(now()->subDays(7));
    }

    /** Ambil bacaan pertama yang valid di tiap slot 5 menit, waktu diubah dari UTC ke WIB. */
    private function sampel(array $data): array
    {
        $hasil = [];
        foreach (collect($data)->sortBy('timestamp') as $t) {
            // -999 dan value_error adalah penanda alat error dari vendor.
            if (! is_numeric($t['value'] ?? null) || ! empty($t['value_error']) || $t['value'] <= -999) {
                continue;
            }
            $utc = Carbon::parse($t['timestamp'], 'UTC');
            $hasil[intdiv($utc->timestamp, self::SLOT_DETIK)] ??= [$utc->setTimezone($this->zona())->toDateTimeString(), (float) $t['value']];
        }

        return array_column($hasil, 1, 0);
    }

    private function keBaris(array $nilai, string $kolom): array
    {
        return array_map(fn ($waktu, $v) => ['waktu' => $waktu, $kolom => $v], array_keys($nilai), $nilai);
    }

    private function daftarkanPerangkat(array $daftar): void
    {
        foreach ($daftar as $d) {
            $kode = (string) $d['device'];
            $tipe = $d['tipesensor'] ?? '';

            if ($tipe === 'genset') {
                continue;
            }
            if ($tipe === 'klimat') {
                $this->daftarkanPos($kode, null, JenisPos::Klimatologi, "Klimatologi Bendungan Sermo ({$kode})");

                continue;
            }

            foreach ($d['params'] ?? [] as $param => $meta) {
                if (empty($meta['group'])) {
                    continue; // kanal diagnostik logger: baterai, sinyal, suhu logger
                }
                $satuan = html_entity_decode((string) ($meta['satuan'] ?? '')) ?: null;

                if ($tipe === 'awlr' && in_array($satuan, ['m', 'mdpl'], true)) {
                    $nama = ($meta['keterangan'] ?? null) ?: (($meta['label'] ?? null) ?: "AWLR Sermo {$kode} {$param}");
                    $this->daftarkanPos($kode, (string) $param, JenisPos::Awlr, $nama);
                } else {
                    ParameterInstrumen::updateOrCreate(
                        ['sumber' => $this->sumber(), 'kode_perangkat' => $kode, 'kode_parameter' => (string) $param],
                        [
                            'tipe_sensor' => $tipe ?: null,
                            'grup' => $meta['group'],
                            'label' => ($meta['label'] ?? null) ?: null,
                            'keterangan' => ($meta['keterangan'] ?? null) ?: null,
                            'satuan' => $satuan,
                            'batas_alarm' => $meta['alarm_levels'] ?? null,
                            'tampil' => (bool) ($meta['is_show'] ?? true),
                        ],
                    );
                }
            }
        }
    }

    /** Buat pos + perangkat_telemetri bila belum ada. Pos yang sudah ada tidak diubah. */
    private function daftarkanPos(string $kode, ?string $sensor, JenisPos $jenis, string $nama): void
    {
        $sudahAda = PerangkatTelemetri::where(['sumber' => $this->sumber(), 'kode_perangkat' => $kode, 'sensor' => $sensor])->exists();
        if ($sudahAda) {
            return;
        }

        // Alat yang sama bisa sudah terdaftar lewat vendor lain (SOWL024/025 lewat PCB Jogja): pakai pos itu.
        $posId = PerangkatTelemetri::where('kode_perangkat', $kode)->where('sumber', '!=', $this->sumber())->value('pos_id')
            ?? Pos::create([
                'walidata_id' => config('services.sermo.walidata_id'),
                'kode' => $kode,
                'nama' => $nama,
                'tipe' => $jenis->tipe(),
                'jenis' => $jenis,
                'metode' => MetodePos::Telemetri,
            ])->id;

        PerangkatTelemetri::create(['pos_id' => $posId, 'sumber' => $this->sumber(), 'kode_perangkat' => $kode, 'sensor' => $sensor]);
    }

    private function api(string $endpoint, array $query = []): array
    {
        try {
            $respons = Http::withToken((string) config('services.sermo.token'))
                ->timeout(30)
                ->get(config('services.sermo.url')."/{$endpoint}", $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }

        // Respons Sermo diawali BOM.
        $data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $respons->body()), true);
        if ($respons->failed() || ! is_array($data) || isset($data['header'])) {
            throw new RuntimeException($data['header']['detail'] ?? "HTTP {$respons->status()}");
        }

        return $data;
    }
}

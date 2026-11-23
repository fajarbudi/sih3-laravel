<?php

namespace App\Services\Telemetri;

use App\Enums\SumberTelemetri;
use App\Models\LogTelemetri;
use App\Models\PerangkatTelemetri;
use App\Services\RekapTelemetri;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dasar semua kelas FetchData telemetri. Turunannya cukup mengambil data dari API vendor
 * lalu memanggil simpan(); sisanya (nilai terakhir, rekap per jam, log gagal) diurus di sini.
 *
 * Kolom `waktu` disimpan sebagai jam WIB, sama dengan data hasil impor.
 */
abstract class FetchData
{
    public int $gagal = 0;

    public function __construct(protected RekapTelemetri $rekap) {}

    /** Ambil data dari vendor. Mengembalikan jumlah baris yang disimpan. */
    abstract public function fetch(?CarbonInterface $dari, ?CarbonInterface $sampai): int;

    abstract protected function sumber(): SumberTelemetri;

    /** Perangkat aktif milik sumber ini, beserta posnya (termasuk pos yang di-soft-delete). */
    protected function perangkat(): Collection
    {
        return PerangkatTelemetri::with(['pos' => fn ($q) => $q->withTrashed()])
            ->where('sumber', $this->sumber())
            ->where('aktif', true)
            ->get();
    }

    /**
     * Simpan bacaan satu perangkat. $baris: [['waktu' => ..., 'hujan'|'tma'|... => nilai], ...]
     * Default baris yang sudah ada dilewati; $perbarui = true menimpanya (untuk nilai per jam yang masih berubah).
     */
    protected function simpan(PerangkatTelemetri $perangkat, string $tabel, array $baris, bool $perbarui = false): int
    {
        if (! $baris) {
            return 0;
        }

        $baris = array_map(fn ($b) => $b + [
            'pos_id' => $perangkat->pos_id,
            'sumber' => $this->sumber()->value,
            'diterima_at' => now(),
        ], $baris);

        $jumlah = 0;
        foreach (array_chunk($baris, 500) as $potongan) {
            $jumlah += $perbarui
                ? DB::table($tabel)->upsert($potongan, ['pos_id', 'waktu', 'sumber'], array_keys(array_diff_key($potongan[0], array_flip(['pos_id', 'waktu', 'sumber']))))
                : DB::table($tabel)->insertOrIgnore($potongan);
        }

        $this->perbaruiTerakhir($perangkat, $tabel, $baris);

        return $perbarui ? count($baris) : $jumlah;
    }

    private function perbaruiTerakhir(PerangkatTelemetri $perangkat, string $tabel, array $baris): void
    {
        $waktu = array_column($baris, 'waktu');
        $terbaru = $baris[array_search(max($waktu), $waktu)];

        // Dibandingkan sebagai waktu (bukan string) karena format waktu vendor berbeda-beda.
        if (! $perangkat->terakhir_diterima_at || $perangkat->terakhir_diterima_at->lessThanOrEqualTo(Carbon::parse($terbaru['waktu']))) {
            $perangkat->update([
                'terakhir_diterima_at' => $terbaru['waktu'],
                'nilai_terakhir' => $terbaru['hujan'] ?? $terbaru['tma'] ?? null,
            ]);
        }

        $dari = Carbon::parse(min($waktu));
        $sampai = Carbon::parse(max($waktu));
        match ($tabel) {
            'telemetri_arr' => $this->rekap->hitungUlangArr($dari, $sampai, $perangkat->pos_id),
            'telemetri_awlr' => $this->rekap->hitungUlangAwlr($dari, $sampai, $perangkat->pos_id),
            default => null,
        };
    }

    /** Waktu bacaan terakhir yang sudah tersimpan untuk perangkat ini. */
    protected function waktuTerakhir(PerangkatTelemetri $perangkat, string $tabel): ?CarbonInterface
    {
        $waktu = DB::table($tabel)
            ->where('pos_id', $perangkat->pos_id)
            ->where('sumber', $this->sumber()->value)
            ->max('waktu');

        return $waktu ? Carbon::parse($waktu, $this->zona()) : null;
    }

    /**
     * Potong rentang menjadi beberapa bagian, karena API vendor membatasi panjang rentang.
     *
     * @return list<array{0: CarbonInterface, 1: CarbonInterface}>
     */
    protected function potongRentang(CarbonInterface $dari, CarbonInterface $sampai, int $maksHari): array
    {
        $hasil = [];
        for ($a = $dari->copy(); $a->lessThanOrEqualTo($sampai); $a = $b->copy()->addSecond()) {
            $b = $a->copy()->addDays($maksHari)->subSecond()->min($sampai);
            $hasil[] = [$a, $b];
        }

        return $hasil;
    }

    /**
     * Jalankan satu permintaan ke vendor. Timeout/koneksi putus hanya dicatat sebagai gagal untuk perangkat itu,
     * agar perangkat lain dalam tarikan yang sama tetap diambil. Mengembalikan null bila gagal terhubung.
     */
    protected function coba(?string $kodePerangkat, callable $permintaan): mixed
    {
        try {
            return $permintaan();
        } catch (ConnectionException $e) {
            $this->catatGagal($kodePerangkat, $e->getMessage());

            return null;
        }
    }

    protected function catatGagal(?string $kodePerangkat, string $pesan): void
    {
        $this->gagal++;
        LogTelemetri::create([
            'sumber' => $this->sumber()->value,
            'kode_perangkat' => $kodePerangkat,
            'pesan' => Str::limit($pesan, 250),
        ]);
    }

    protected function zona(): string
    {
        return config('services.telemetri.zona_waktu');
    }
}

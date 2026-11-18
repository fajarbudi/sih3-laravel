<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Models\PermintaanDataPos;
use App\Models\Pos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard kondisi lapangan: hujan, muka air, dan cuaca.
 * Kolom waktu telemetri disimpan sebagai jam WIB (lihat FetchData), jadi semua batas waktu dihitung dalam WIB.
 */
class DashboardController extends Controller
{
    /** Perubahan muka air (meter) yang dianggap naik/turun, bukan sekadar fluktuasi sensor. */
    private const AMBANG_PERUBAHAN_M = 0.05;

    /** Kategori curah hujan harian BMKG (mm/hari): batas bawah => nama. */
    public const KATEGORI_HUJAN = [150 => 'Ekstrem', 100 => 'Sangat lebat', 50 => 'Lebat', 20 => 'Sedang', 5 => 'Ringan', 0 => 'Sangat ringan'];

    public function __invoke(Request $request): View
    {
        $pengguna = $request->user();
        $zona = config('services.telemetri.zona_waktu');
        $posId = Pos::terlihatOleh($pengguna)->select('id');

        $hujanPerPos = DB::table('rekap_arr_per_jam as r')
            ->join('pos', 'pos.id', '=', 'r.pos_id')
            ->whereIn('r.pos_id', $posId)
            ->where('r.jam', '>=', now($zona)->subDay()->startOfHour()->toDateTimeString())
            ->selectRaw('pos.nama, sum(r.hujan) as hujan')
            ->groupBy('pos.id', 'pos.nama')
            ->having('hujan', '>', 0)
            ->orderByDesc('hujan')
            ->limit(10)
            ->pluck('hujan', 'nama')
            ->map(fn ($h) => round((float) $h, 1));

        $mukaAir = $this->mukaAir($posId, $zona);

        return view('admin.dashboard', [
            'sekarang' => now($zona),
            'ambangPerubahan' => self::AMBANG_PERUBAHAN_M,
            'jumlahMenunggu' => PermintaanDataPos::whereIn('pos_id', $posId)->where('status', StatusPermintaan::Pengajuan)->count(),
            'hujanPerJam' => $this->hujanPerJam($posId, $zona),
            'hujanPerPos' => $hujanPerPos,
            'mukaAir' => $mukaAir,
            'posNaik' => $mukaAir->filter(fn ($m) => $m['perubahan'] !== null && $m['perubahan'] >= self::AMBANG_PERUBAHAN_M)->count(),
            'cuaca' => $this->cuaca($posId),
        ]);
    }

    public static function kategoriHujan(float $mm): string
    {
        foreach (self::KATEGORI_HUJAN as $bawah => $nama) {
            if ($mm >= $bawah) {
                return $nama;
            }
        }

        return 'Sangat ringan';
    }

    /**
     * Curah hujan tertinggi di antara semua pos untuk setiap jam, 48 jam terakhir.
     *
     * @return list<array{jam: string, hujan: float, pos: ?string}>
     */
    private function hujanPerJam($posId, string $zona): array
    {
        $mulai = now($zona)->subHours(47)->startOfHour();
        $baris = DB::table('rekap_arr_per_jam as r')
            ->join('pos', 'pos.id', '=', 'r.pos_id')
            ->whereIn('r.pos_id', $posId)
            ->where('r.jam', '>=', $mulai->toDateTimeString())
            ->where('r.hujan', '>', 0)
            ->get(['r.jam', 'r.hujan', 'pos.nama'])
            ->groupBy(fn ($r) => Carbon::parse($r->jam)->format('Y-m-d H:00'))
            ->map(fn ($jam) => $jam->sortByDesc('hujan')->first());

        return collect(range(0, 47))->map(function ($i) use ($mulai, $baris) {
            $kunci = $mulai->copy()->addHours($i)->format('Y-m-d H:00');

            return ['jam' => $kunci, 'hujan' => round((float) ($baris[$kunci]->hujan ?? 0), 1), 'pos' => $baris[$kunci]->nama ?? null];
        })->all();
    }

    /**
     * Muka air terkini per pos AWLR telemetri yang mengirim data dalam 7 hari terakhir, dalam meter
     * (nilai mentah x faktor_skala). Pos yang naik paling tinggi di urutan pertama.
     *
     * @return Collection<int, array{nama: string, tma: float, perubahan: ?float, waktu: Carbon, jejak: list<float>}>
     */
    private function mukaAir($posId, string $zona): Collection
    {
        $pos = Pos::whereIn('id', $posId)->jenis(JenisPos::Awlr)->with('perangkatTelemetri')->get()->keyBy('id');

        // Bacaan terbaru per pos (sumber yang paling baru mengirim).
        $terbaru = DB::table('telemetri_awlr')
            ->whereIn('pos_id', $pos->keys())
            ->where('waktu', '>=', now($zona)->subDays(7)->toDateTimeString())
            ->selectRaw('pos_id, sumber, max(waktu) as waktu')
            ->groupBy('pos_id', 'sumber')
            ->get()
            ->sortByDesc('waktu')
            ->unique('pos_id');

        return $terbaru->map(function ($t) use ($pos, $zona) {
            $faktor = $pos[$t->pos_id]->perangkatTelemetri->firstWhere('sumber.value', $t->sumber)?->faktor_skala ?? 1;
            $bacaan = fn ($operator, $waktu) => DB::table('telemetri_awlr')
                ->where(['pos_id' => $t->pos_id, 'sumber' => $t->sumber])
                ->where('waktu', $operator, $waktu)
                ->orderByDesc('waktu')
                ->value('tma');

            $kini = (float) $bacaan('<=', $t->waktu) * $faktor;
            $lalu = $bacaan('<=', Carbon::parse($t->waktu)->subDay()->toDateTimeString());

            return [
                'nama' => $pos[$t->pos_id]->nama,
                'tma' => round($kini, 2),
                'perubahan' => $lalu === null ? null : round($kini - (float) $lalu * $faktor, 2),
                'waktu' => Carbon::parse($t->waktu, $zona),
                'jejak' => DB::table('rekap_awlr_per_jam')
                    ->where('pos_id', $t->pos_id)
                    ->where('jam', '>=', Carbon::parse($t->waktu)->subDay()->toDateTimeString())
                    ->orderBy('jam')
                    ->pluck('tma_rata')
                    ->map(fn ($v) => round((float) $v * $faktor, 3))
                    ->all(),
            ];
        })->sortByDesc(fn ($m) => $m['perubahan'] ?? -INF)->values();
    }

    /** Bacaan klimatologi terbaru per pos (7 hari terakhir). */
    private function cuaca($posId): Collection
    {
        return DB::table('telemetri_klimatologi as k')
            ->join('pos', 'pos.id', '=', 'k.pos_id')
            ->whereIn('k.pos_id', $posId)
            ->where('k.waktu', '>=', now(config('services.telemetri.zona_waktu'))->subDays(7)->toDateTimeString())
            ->orderByDesc('k.waktu')
            ->get(['pos.nama', 'k.pos_id', 'k.waktu', 'k.suhu', 'k.kelembapan', 'k.kecepatan_angin', 'k.hujan'])
            ->unique('pos_id')
            ->values();
    }
}

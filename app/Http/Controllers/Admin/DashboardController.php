<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Models\Buletin;
use App\Models\PerangkatTelemetri;
use App\Models\PermintaanDataPos;
use App\Models\Pos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Dashboard berbasis grafik. Setiap grafik menjawab satu pertanyaan (judul kartunya).
 * Kolom waktu telemetri disimpan sebagai jam WIB (lihat FetchData), jadi semua batas waktu dihitung dalam WIB.
 */
class DashboardController extends Controller
{
    /** Perangkat dianggap diam bila tidak ada data selama ini. */
    private const BATAS_DIAM_JAM = 3;

    public function __invoke(Request $request): View
    {
        $pengguna = $request->user();
        $zona = config('services.telemetri.zona_waktu');
        $posId = Pos::terlihatOleh($pengguna)->select('id');

        $batas = now($zona)->subHours(self::BATAS_DIAM_JAM)->toDateTimeString();
        $perangkat = PerangkatTelemetri::whereIn('pos_id', $posId)->where('aktif', true)->get();
        $aktif = fn ($p) => $p->terakhir_diterima_at && $p->terakhir_diterima_at->toDateTimeString() >= $batas;

        return view('admin.dashboard', [
            'sekarang' => now($zona),
            'batasDiamJam' => self::BATAS_DIAM_JAM,
            'perangkatAktif' => $perangkat->filter($aktif)->count(),
            'jumlahPerangkat' => $perangkat->count(),
            'jumlahMenunggu' => PermintaanDataPos::whereIn('pos_id', $posId)->where('status', StatusPermintaan::Pengajuan)->count(),
            'buletinDraf' => $pengguna->isSuperadmin() ? Buletin::where('tipe', 'otomatis')->where('terbit', false)->count() : null,

            'hujanPerJam' => $this->hujanPerJam($posId, $zona),
            'hujanPerPos' => DB::table('rekap_arr_per_jam as r')
                ->join('pos', 'pos.id', '=', 'r.pos_id')
                ->whereIn('r.pos_id', $posId)
                ->where('r.jam', '>=', now($zona)->subDay()->startOfHour()->toDateTimeString())
                ->selectRaw('pos.nama, sum(r.hujan) as hujan')
                ->groupBy('pos.id', 'pos.nama')
                ->having('hujan', '>', 0)
                ->orderByDesc('hujan')
                ->limit(10)
                ->pluck('hujan', 'nama')
                ->map(fn ($h) => round((float) $h, 1)),
            'bacaanPerHari' => $this->bacaanPerHari($posId, $zona),
            'statusSumber' => $perangkat->groupBy(fn ($p) => $p->sumber->label())
                ->map(fn ($daftar) => ['aktif' => $daftar->filter($aktif)->count(), 'jumlah' => $daftar->count()])
                ->sortKeys(),
            'posPerJenis' => Pos::terlihatOleh($pengguna)
                ->selectRaw('jenis, count(*) as jumlah')
                ->groupBy('jenis')
                ->orderByDesc('jumlah')
                ->get()
                ->mapWithKeys(fn ($p) => [$p->jenis->label() => (int) $p->jumlah]),
        ]);
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
     * Jumlah bacaan telemetri tersimpan per hari, per jenis data, 14 hari terakhir.
     *
     * @return array{tanggal: list<string>, seri: array<string, list<int>>}
     */
    private function bacaanPerHari($posId, string $zona): array
    {
        $hari = collect(range(13, 0))->map(fn ($i) => now($zona)->subDays($i)->toDateString());
        $seri = [];
        foreach (['Curah hujan' => 'telemetri_arr', 'Muka air' => 'telemetri_awlr', 'Klimatologi' => 'telemetri_klimatologi'] as $nama => $tabel) {
            $jumlah = DB::table($tabel)
                ->whereIn('pos_id', $posId)
                ->where('waktu', '>=', $hari->first())
                ->selectRaw('DATE(waktu) as tanggal, count(*) as jumlah')
                ->groupBy('tanggal')
                ->pluck('jumlah', 'tanggal');
            $seri[$nama] = $hari->map(fn ($t) => (int) ($jumlah[$t] ?? 0))->all();
        }

        return ['tanggal' => $hari->all(), 'seri' => $seri];
    }
}

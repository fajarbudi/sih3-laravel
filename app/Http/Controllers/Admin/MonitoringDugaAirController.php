<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Http\Controllers\Controller;
use App\Models\PengamatanAwlr;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Monitoring pengisian duga air manual (asal: Mawlr.php). Seperti aplikasi lama: semua pos AWLR
 * urut nama (50 per halaman), pembacaan kemarin jam 7/12/17 (limpas, intake kanan, intake kiri, rata-rata),
 * "x" bila tidak ada, baris pos yang sudah mengisi berlatar hijau.
 *
 * Perbedaan dengan aplikasi lama: judul kolom dikelompokkan per jam sesuai isinya (dulu judul per besaran
 * tidak cocok dengan isi), tanggal bisa dipilih (default tetap kemarin), dan ada ringkasan jumlah pos yang mengisi.
 */
class MonitoringDugaAirController extends Controller
{
    public const JAM = [7, 12, 17];

    /** Kolom per jam, urutan seperti isi tabel aplikasi lama. */
    public const NILAI = ['limpas' => 'Limpas', 'intake_kanan' => 'Intake Kanan', 'intake_kiri' => 'Intake Kiri'];

    public function __invoke(Request $request): View
    {
        $filter = array_filter($request->validate([
            'walidata' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]), fn ($v) => $v !== null && $v !== '');
        $tanggal = Carbon::parse($filter['tanggal'] ?? today()->subDay());
        $superadmin = $request->user()->isSuperadmin();

        $semuaPos = Pos::terlihatOleh($request->user())->jenis(JenisPos::Awlr)
            ->when($superadmin ? ($filter['walidata'] ?? null) : null, fn ($q, $walidata) => $q->where('walidata_id', $walidata))
            ->when($filter['cari'] ?? null, fn ($q, $cari) => $q->where('nama', 'like', '%'.$cari.'%'));

        $pos = (clone $semuaPos)->with('walidata:id,nama')->orderBy('nama')->paginate(50)->withQueryString();

        // Pembacaan tanggal itu: [pos_id][jam] => pengamatan.
        $bacaan = PengamatanAwlr::whereIn('pos_id', (clone $semuaPos)->select('id'))
            ->whereBetween('waktu', [$tanggal->copy()->startOfDay(), $tanggal->copy()->endOfDay()])
            ->get()
            ->groupBy('pos_id')
            ->map(fn ($p) => $p->keyBy(fn ($b) => $b->waktu->hour));

        return view('admin.monitoring-duga-air', [
            'pos' => $pos,
            'bacaan' => $bacaan,
            'tanggal' => $tanggal,
            'jumlahPos' => (clone $semuaPos)->count(),
            'filter' => $filter,
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
        ]);
    }
}

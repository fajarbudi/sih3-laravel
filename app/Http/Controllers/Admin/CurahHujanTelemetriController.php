<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Http\Controllers\Controller;
use App\Models\Pos;
use App\Models\RekapArrPerJam;
use App\Models\Walidata;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Curah hujan telemetri per jam dalam satu "hari hujan" (asal: Arrtele.php). Seperti aplikasi lama:
 * tanggal default hari ini; kolom jam 07-23 hari sebelumnya lalu 00-06 tanggal terpilih, plus Total;
 * baris = pos ARR yang punya data (nama + walidata); angka 2 desimal; filter walidata dan pos.
 *
 * Perbedaan dengan aplikasi lama: nilai diambil per tanggal+jam (dulu per jam saja, sehingga jam yang sama dari
 * dua hari bisa tertukar), sumber = rekap_arr_per_jam (dihitung ulang, bukan ana_arr_all yang menggelembung),
 * dan baris diurutkan menurut nama pos.
 */
class CurahHujanTelemetriController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filter = array_filter($request->validate([
            'walidata' => ['nullable', 'integer'],
            'pos' => ['nullable', 'integer'],
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]), fn ($v) => $v !== null && $v !== '');
        $tanggal = Carbon::parse($filter['tanggal'] ?? today());
        $superadmin = $request->user()->isSuperadmin();

        // Hari hujan: 07.00 hari sebelumnya s.d. 06.59 tanggal terpilih.
        $jamAwal = $tanggal->copy()->subDay()->setTime(7, 0);
        $daftarJam = collect(range(0, 23))->map(fn ($i) => $jamAwal->copy()->addHours($i));

        $posTerlihat = Pos::terlihatOleh($request->user())->jenis(JenisPos::Arr)
            ->when($superadmin ? ($filter['walidata'] ?? null) : null, fn ($q, $walidata) => $q->where('walidata_id', $walidata));

        $rekap = RekapArrPerJam::whereIn('pos_id', (clone $posTerlihat)->when($filter['pos'] ?? null, fn ($q, $id) => $q->whereKey($id))->select('id'))
            ->whereBetween('jam', [$jamAwal, $jamAwal->copy()->addHours(23)])
            ->get()
            ->groupBy('pos_id')
            ->map(fn ($r) => $r->keyBy(fn ($b) => $b->jam->format('Y-m-d H')));

        $pos = Pos::with('walidata:id,nama')->whereKey($rekap->keys())->orderBy('nama')->get();

        return view('admin.curah-hujan-telemetri', [
            'pos' => $pos,
            'rekap' => $rekap,
            'daftarJam' => $daftarJam,
            'tanggal' => $tanggal,
            'filter' => $filter,
            'daftarPos' => $posTerlihat->orderBy('nama')->pluck('nama', 'id'),
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Http\Controllers\Controller;
use App\Models\PengamatanArr;
use App\Models\PengamatanAwlr;
use App\Models\PengamatanKualitasAir;
use App\Models\PengamatanMat;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ketersediaan data pengamatan manual (asal: Sedia.php). Seperti aplikasi lama: tabel pos x bulan (Jan-Des)
 * berisi JUMLAH data per bulan dalam satu tahun (default tahun berjalan); hanya pos yang punya data;
 * filter walidata, pos, tahun.
 *
 * Perbedaan dengan aplikasi lama: data dibatasi pada pos yang boleh dilihat pengguna (dulu semua pos)
 * dan baris diurutkan menurut nama pos.
 */
class KetersediaanController extends Controller
{
    public const JENIS = [
        'duga-air' => ['judul' => 'Duga Air (AWLR)', 'model' => PengamatanAwlr::class, 'pos' => JenisPos::Awlr],
        'curah-hujan' => ['judul' => 'Curah Hujan (ARR)', 'model' => PengamatanArr::class, 'pos' => JenisPos::Arr],
        'kualitas-air' => ['judul' => 'Kualitas Air', 'model' => PengamatanKualitasAir::class, 'pos' => JenisPos::KualitasAir],
        'muka-air-tanah' => ['judul' => 'Kedudukan Muka Air Tanah (MAT)', 'model' => PengamatanMat::class, 'pos' => JenisPos::Mat],
    ];

    public function __invoke(Request $request, string $jenis): View
    {
        $ketersediaan = self::JENIS[$jenis];
        $filter = array_filter($request->validate([
            'walidata' => ['nullable', 'integer'],
            'pos' => ['nullable', 'integer'],
            'tahun' => ['nullable', 'integer', 'between:2000,'.now()->year],
        ]), fn ($v) => $v !== null && $v !== '');
        $tahun = (int) ($filter['tahun'] ?? now()->year);
        $superadmin = $request->user()->isSuperadmin();

        $posTerlihat = Pos::terlihatOleh($request->user())->jenis($ketersediaan['pos'])
            ->when($superadmin ? ($filter['walidata'] ?? null) : null, fn ($q, $w) => $q->where('walidata_id', $w));

        // Jumlah data per pos per bulan.
        $jumlah = $ketersediaan['model']::whereIn('pos_id', (clone $posTerlihat)->when($filter['pos'] ?? null, fn ($q, $id) => $q->whereKey($id))->select('id'))
            ->whereBetween('waktu', [now()->setDate($tahun, 1, 1)->startOfDay(), now()->setDate($tahun, 12, 31)->endOfDay()])
            ->get(['pos_id', 'waktu'])
            ->groupBy('pos_id')
            ->map(fn ($p) => $p->countBy(fn ($b) => $b->waktu->month));

        return view('admin.ketersediaan', [
            'jenis' => $jenis,
            'judul' => $ketersediaan['judul'],
            'tahun' => $tahun,
            'jumlah' => $jumlah,
            'pos' => Pos::whereKey($jumlah->keys())->orderBy('nama')->get(['id', 'nama']),
            'filter' => $filter,
            'daftarPos' => $posTerlihat->orderBy('nama')->pluck('nama', 'id'),
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
        ]);
    }
}

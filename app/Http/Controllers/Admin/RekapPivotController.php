<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\PengamatanArr;
use App\Models\PengamatanMat;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rekapitulasi berbentuk pivot setahun (asal: Rarr.php dan Rmat.php). Seperti aplikasi lama:
 * filter walidata/pos/tahun (tahun default tahun berjalan), kolom Jan s.d. Des, isi sel = rata-rata
 * pembacaan (2 desimal), semua baris tanpa paginasi, plus Cetak dan Excel.
 * - Curah hujan: satu baris per pos per tanggal (1-31), sel = rata-rata hari itu.
 * - Muka air tanah: satu baris per pos, sel = rata-rata bulan itu.
 *
 * Perbedaan dengan aplikasi lama: baris diurutkan menurut nama pos lalu tanggal (dulu mengikuti urutan input),
 * rata-rata mengabaikan pembacaan kosong (dulu dihitung 0), dan data dibatasi pada pos yang boleh dilihat pengguna.
 */
class RekapPivotController extends Controller
{
    public const JENIS = [
        'curah-hujan' => ['judul' => 'Rekap Curah Hujan', 'induk' => 'Hidrometeorologi', 'model' => PengamatanArr::class,
            'pos' => JenisPos::Arr, 'kolom' => 'hujan', 'satuan' => 'mm', 'perHari' => true],
        'muka-air-tanah' => ['judul' => 'Rekap Muka Air Tanah', 'induk' => 'Hidrogeologi', 'model' => PengamatanMat::class,
            'pos' => JenisPos::Mat, 'kolom' => 'muka_air_tanah', 'satuan' => 'M', 'perHari' => false],
    ];

    public const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function __invoke(Request $request, string $jenis): Response
    {
        $rekap = self::JENIS[$jenis];
        $filter = $request->validate([
            'walidata' => ['nullable', 'integer'],
            'pos' => ['nullable', 'integer'],
            'tahun' => ['nullable', 'integer', 'between:2000,'.now()->year],
            'format' => ['nullable', Rule::in(['cetak', 'excel'])],
        ]);
        $filter['tahun'] = (int) ($filter['tahun'] ?? now()->year);
        $superadmin = $request->user()->isSuperadmin();
        $posTerlihat = fn () => Pos::terlihatOleh($request->user())->jenis($rekap['pos']);

        $pos = $posTerlihat()
            ->when($superadmin ? ($filter['walidata'] ?? null) : null, fn ($q, $walidata) => $q->where('walidata_id', $walidata))
            ->when($filter['pos'] ?? null, fn ($q, $id) => $q->whereKey($id));

        $pengamatan = $rekap['model']::with('pos:id,nama')
            ->whereIn('pos_id', $pos->select('id'))
            ->whereBetween('waktu', [now()->setDate($filter['tahun'], 1, 1)->startOfDay(), now()->setDate($filter['tahun'], 12, 31)->endOfDay()])
            ->whereNotNull($rekap['kolom'])
            ->get(['id', 'pos_id', 'waktu', $rekap['kolom']]);

        // Baris = pos (dan tanggal untuk curah hujan); sel = rata-rata per bulan.
        $baris = $pengamatan
            ->groupBy(fn ($p) => $p->pos_id.($rekap['perHari'] ? '-'.$p->waktu->day : ''))
            ->map(fn ($kelompok) => [
                'pos' => $kelompok->first()->pos->nama,
                'tanggal' => $kelompok->first()->waktu->day,
                'bulan' => $kelompok->groupBy(fn ($p) => $p->waktu->month)->map(fn ($b) => $b->avg($rekap['kolom']))->all(),
            ])
            ->sortBy([['pos', 'asc'], ['tanggal', 'asc']])
            ->values();

        $isi = [
            'baris' => $baris,
            'jenis' => $jenis,
            'rekap' => $rekap,
            'judul' => str_replace('Rekap', 'Rekapitulasi', $rekap['judul']).' Tahun '.$filter['tahun'],
            'tampilPos' => $request->user()->peran !== PeranPengguna::PetugasPos,
            'tabel' => 'admin.rekap.pivot-tabel',
        ];

        return match ($filter['format'] ?? null) {
            'cetak' => response()->view('admin.rekap.cetak', $isi + ['data' => $baris]),
            'excel' => response()->view('admin.rekap.cetak', $isi + ['data' => $baris, 'excel' => true])
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="rekap-'.$jenis.'-'.$filter['tahun'].'.xls"'),
            default => response()->view('admin.rekap.pivot', $isi + [
                'filter' => $filter,
                'daftarPos' => $posTerlihat()->orderBy('nama')->pluck('nama', 'id'),
                'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            ]),
        };
    }
}

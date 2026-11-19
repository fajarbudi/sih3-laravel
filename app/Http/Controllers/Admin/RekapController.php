<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\PengamatanAwlr;
use App\Models\PengamatanKlimatologi;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rekapitulasi berbentuk daftar (asal: Rawlr.php dan Rklimat.php). Seperti aplikasi lama:
 * daftar baca-saja satu bulan, filter walidata/pos/bulan (bulan default bulan berjalan),
 * semua baris tanpa paginasi, urut terakhir diinput, plus Cetak dan Excel.
 *
 * Perbedaan dengan aplikasi lama: data dibatasi pada pos yang boleh dilihat pengguna
 * (dulu rekap menampilkan semua pos ke semua akun), dan Excel berupa tabel .xls tanpa library tambahan.
 */
class RekapController extends Controller
{
    /** Jenis rekap => judul, model, jenis pos, kolom nilai => label (label sama dengan aplikasi lama). */
    public const JENIS = [
        'duga-air' => [
            'judul' => 'Rekap Duga Air', 'induk' => 'Hidrologi', 'model' => PengamatanAwlr::class, 'pos' => JenisPos::Awlr,
            'nilai' => [
                'limpas' => 'Muka Air Limpas (m)',
                'intake_kiri' => 'Muka Air Intake Kiri (m)',
                'intake_kanan' => 'Muka Air Intake Kanan (m)',
                'debit_pdam' => 'Muka Air PDAM (m)',
                'pembilas_kiri' => 'Muka Air Pembilas Kiri (m)',
                'pembilas_kanan' => 'Muka Air Pembilas Kanan (m)',
                'debit_suplesi' => 'Muka Air Suplesi (m)',
            ],
        ],
        'klimatologi' => [
            'judul' => 'Rekap Klimatologi', 'induk' => 'Hidrometeorologi', 'model' => PengamatanKlimatologi::class, 'pos' => JenisPos::Klimatologi,
            'nilai' => [
                'kelembapan' => 'RH',
                'suhu_max' => 'Temperatur Maksimal',
                'suhu_min' => 'Temperatur Minimal',
                'suhu_rata' => 'Temperatur Rata-rata',
                'suhu_air' => 'Temperatur Air',
                'penguapan' => 'Penguapan',
                'kecepatan_angin' => 'Kecepatan Angin',
                'radiasi' => 'Radiasi Matahari',
                'penyinaran' => 'Lama Penyinaran',
                'hujan' => 'Hujan',
            ],
        ],
    ];

    public function __invoke(Request $request, string $jenis): Response
    {
        $rekap = self::JENIS[$jenis];
        $filter = $request->validate([
            'walidata' => ['nullable', 'integer'],
            'pos' => ['nullable', 'integer'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'format' => ['nullable', Rule::in(['cetak', 'excel'])],
        ]);
        $filter['bulan'] ??= now()->format('Y-m');
        $superadmin = $request->user()->isSuperadmin();
        $awal = Carbon::createFromFormat('Y-m', $filter['bulan'])->startOfMonth();
        $posTerlihat = fn () => Pos::terlihatOleh($request->user())->jenis($rekap['pos']);

        $pos = $posTerlihat()
            ->when($superadmin ? ($filter['walidata'] ?? null) : null, fn ($q, $walidata) => $q->where('walidata_id', $walidata))
            ->when($filter['pos'] ?? null, fn ($q, $id) => $q->whereKey($id));

        $data = $rekap['model']::with('pos:id,nama')
            ->whereIn('pos_id', $pos->select('id'))
            ->whereBetween('waktu', [$awal, $awal->copy()->endOfMonth()])
            ->orderByDesc('id')
            ->get();

        $isi = [
            'data' => $data,
            'jenis' => $jenis,
            'rekap' => $rekap,
            'judul' => str_replace('Rekap', 'Rekapitulasi', $rekap['judul']).' '.$awal->translatedFormat('F Y'),
            'tampilPos' => $request->user()->peran !== PeranPengguna::PetugasPos,
        ];

        return match ($filter['format'] ?? null) {
            'cetak' => response()->view('admin.rekap.cetak', $isi),
            'excel' => response()->view('admin.rekap.cetak', $isi + ['excel' => true])
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="rekap-'.$jenis.'-'.$filter['bulan'].'.xls"'),
            default => response()->view('admin.rekap.index', $isi + [
                'filter' => $filter,
                'daftarPos' => $posTerlihat()->orderBy('nama')->pluck('nama', 'id'),
                'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            ]),
        };
    }
}

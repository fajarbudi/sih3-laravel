<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\SumberTelemetri;
use App\Http\Controllers\Controller;
use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use App\Models\TelemetriArr;
use App\Models\TelemetriAwlr;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Data telemetri Perum Jasa Tirta I (asal: Awlrpjt.php dan Arrpjt.php). Seperti aplikasi lama: daftar baca-saja
 * POS, Waktu, nilai, urut waktu terbaru, 100 per halaman, filter pos (pos PJT) dan bulan.
 *
 * Perbedaan dengan aplikasi lama:
 * - Hanya pos walidata PJT (tabel lama ana_telemetri_* juga berisi salinan pos vendor lain; keputusan user).
 * - Tanpa tombol ubah/tambah/unggah file: data telemetri tidak diubah manual.
 * - Nilai = nilai mentah x faktor_skala perangkat (PJT = 1: TMA meter, hujan mm).
 */
class TelemetriPjtController extends Controller
{
    public const WALIDATA_PJT = 11;

    public const JENIS = [
        'duga-air-pjt' => ['judul' => 'Duga Air PJT', 'induk' => 'Hidrologi', 'model' => TelemetriAwlr::class,
            'pos' => JenisPos::Awlr, 'kolom' => 'tma', 'label' => 'Tinggi Muka Air (m)'],
        'curah-hujan-pjt' => ['judul' => 'Curah Hujan PJT', 'induk' => 'Hidrometeorologi', 'model' => TelemetriArr::class,
            'pos' => JenisPos::Arr, 'kolom' => 'hujan', 'label' => 'Curah Hujan (mm)'],
    ];

    public function __invoke(Request $request, string $jenis): View
    {
        $telemetri = self::JENIS[$jenis];
        $filter = array_filter($request->validate([
            'pos' => ['nullable', 'integer'],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]), fn ($v) => $v !== null && $v !== '');

        $posPjt = Pos::terlihatOleh($request->user())->jenis($telemetri['pos'])->where('walidata_id', self::WALIDATA_PJT);

        $data = $telemetri['model']::with('pos:id,nama')
            ->where('sumber', SumberTelemetri::Pjt)
            ->whereIn('pos_id', (clone $posPjt)->when($filter['pos'] ?? null, fn ($q, $id) => $q->whereKey($id))->select('id'))
            ->when($filter['bulan'] ?? null, function ($q, $bulan) {
                $awal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
                $q->whereBetween('waktu', [$awal, $awal->copy()->endOfMonth()]);
            })
            ->orderByDesc('waktu')
            ->paginate(100)
            ->withQueryString();

        return view('admin.telemetri-pjt', [
            'data' => $data,
            'jenis' => $jenis,
            'telemetri' => $telemetri,
            'faktor' => PerangkatTelemetri::where('sumber', SumberTelemetri::Pjt)->pluck('faktor_skala', 'pos_id'),
            'daftarPos' => $posPjt->orderBy('nama')->pluck('nama', 'id'),
            'filter' => $filter,
        ]);
    }
}

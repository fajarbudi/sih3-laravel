<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Http\Controllers\Controller;
use App\Models\ParameterKualitasAir;
use App\Models\PengamatanKualitasAir;
use App\Models\Pos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rekapitulasi kualitas air (asal: Rkualitas_air.php). Seperti aplikasi lama: satu pos, satu tahun
 * (default tahun berjalan), tabel ditranspos: baris = keterangan (info pos, tanggal, jam, lokasi,
 * parameter, kondisi fisik), kolom = sampel bernomor 1, 2, 3, ... urut terakhir diinput. Tanpa pos
 * yang dipilih tabel kosong. Plus Cetak dan Excel.
 *
 * Perbedaan dengan aplikasi lama: pilihan pos dibatasi pada pos pengguna (petugas otomatis posnya),
 * dan baris hanya berisi kolom yang bermakna (dulu semua kolom tabel pos ikut tampil, termasuk yang teknis).
 */
class RekapKualitasAirController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filter = $request->validate([
            'pos' => ['nullable', 'integer'],
            'tahun' => ['nullable', 'integer', 'between:2000,'.now()->year],
            'format' => ['nullable', Rule::in(['cetak', 'excel'])],
        ]);
        $filter['tahun'] = (int) ($filter['tahun'] ?? now()->year);
        $daftarPos = Pos::terlihatOleh($request->user())->jenis(JenisPos::KualitasAir)->orderBy('nama')->pluck('nama', 'id');

        // Satu pos saja (mis. petugas) langsung terpilih.
        $posId = $daftarPos->count() === 1 ? $daftarPos->keys()->first() : ($filter['pos'] ?? null);
        $pos = $posId && $daftarPos->has($posId) ? Pos::find($posId) : null;

        $sampel = $pos ? PengamatanKualitasAir::with('parameter')
            ->where('pos_id', $pos->id)
            ->whereBetween('waktu', [now()->setDate($filter['tahun'], 1, 1)->startOfDay(), now()->setDate($filter['tahun'], 12, 31)->endOfDay()])
            ->orderByDesc('id')
            ->get() : collect();

        $isi = [
            'sampel' => $sampel,
            'baris' => $pos ? $this->baris($pos) : [],
            'judul' => 'Rekapitulasi Kualitas Air'.($pos ? ' '.$pos->nama : '').' Tahun '.$filter['tahun'],
            'satuanJumlah' => 'sampel',
        ];

        return match ($filter['format'] ?? null) {
            'cetak' => response()->view('admin.rekap.cetak', $isi + ['data' => $sampel, 'tabel' => 'admin.rekap.kualitas-air-tabel']),
            'excel' => response()->view('admin.rekap.cetak', $isi + ['data' => $sampel, 'tabel' => 'admin.rekap.kualitas-air-tabel', 'excel' => true])
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="rekap-kualitas-air-'.($pos?->id ?? 'semua').'-'.$filter['tahun'].'.xls"'),
            default => response()->view('admin.rekap.kualitas-air', $isi + [
                'filter' => ['pos' => $pos?->id] + $filter,
                'daftarPos' => $daftarPos,
            ]),
        };
    }

    /**
     * Baris tabel: label => fungsi pengambil nilai dari satu sampel. Urutan seperti aplikasi lama:
     * info pos, tanggal, jam, lalu isian sampel.
     *
     * @return array<string, \Closure(PengamatanKualitasAir): mixed>
     */
    private function baris(Pos $pos): array
    {
        $baris = [
            'Tipe POS' => fn () => $pos->tipe?->label(),
            'Jenis POS' => fn () => $pos->jenis?->label(),
            'Nama POS' => fn () => $pos->nama,
            'Daerah Aliran Sungai POS' => fn () => $pos->das,
            'Wilayah Sungai POS' => fn () => $pos->wilayah_sungai,
            'Provinsi POS' => fn () => $pos->provinsi,
            'Kabupaten POS' => fn () => $pos->kabupaten,
            'Kecamatan POS' => fn () => $pos->kecamatan,
            'Desa POS' => fn () => $pos->desa,
            'Tanggal' => fn ($s) => $s->waktu->translatedFormat('j F Y'),
            'Jam' => fn ($s) => $s->waktu->format('H:i:s'),
            'Lokasi Titik Pengamatan (Desa)/Kode' => fn ($s) => $s->lokasi,
        ];

        foreach (ParameterKualitasAir::where('aktif', true)->orderBy('urutan')->get() as $p) {
            $label = $p->nama.($p->satuan ? ' ('.$p->satuan.')' : '');
            // float: hanya angka yang diformat di tabel; isian teks (kondisi fisik) tampil apa adanya.
            $baris[$label] = fn ($s) => ($nilai = $s->parameter->firstWhere('id', $p->id)?->pivot->nilai) === null ? null : (float) $nilai;
        }
        $baris['Index Pencemaran'] = fn ($s) => $s->indeks_pencemaran;
        foreach (KualitasAirController::FISIK as $kolom => $label) {
            $baris[$label] = fn ($s) => $s->$kolom;
        }
        $baris['Keterangan'] = fn ($s) => $s->keterangan;

        return $baris;
    }
}

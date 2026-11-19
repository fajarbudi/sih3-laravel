<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\TipePos;
use App\Http\Controllers\Controller;
use App\Models\ArsipPos;
use App\Models\PengamatanArr;
use App\Models\PengamatanAwlr;
use App\Models\Pos;
use App\Models\Walidata;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * File arsip data pos per tahun (asal: Fpos.php + Cetak.php arr/awlr). Seperti aplikasi lama:
 * grid pos x tahun (tahun ini s.d. 10 tahun lalu), unggah/ganti/hapus PDF, dan "Generate PDF" buku data harian setahun
 * untuk pos ARR (jumlah hujan harian) dan AWLR (rata-rata limpas harian). Satu arsip per pos per tahun.
 *
 * Perbedaan dengan aplikasi lama: sel membuka halaman kelola (dulu modal), file dibuka lewat route
 * (bukan URL publik langsung), unggahan hanya PDF, dan "Jml. Hari Hujan" hanya menghitung hari dengan hujan > 0
 * (dulu ikut menghitung hari bernilai 0).
 */
class FileArsipController extends Controller
{
    public const RENTANG_TAHUN = 10;

    public function index(Request $request): View
    {
        $filter = array_filter($request->validate([
            'walidata' => ['nullable', 'integer'],
            'tipe' => ['nullable', Rule::enum(TipePos::class)],
            'jenis' => ['nullable', Rule::enum(JenisPos::class)],
            'cari' => ['nullable', 'string', 'max:100'],
        ]), fn ($v) => $v !== null && $v !== '');
        $superadmin = $request->user()->isSuperadmin();

        $pos = Pos::terlihatOleh($request->user())->with('walidata:id,nama')
            ->when($superadmin ? ($filter['walidata'] ?? null) : null, fn ($q, $w) => $q->where('walidata_id', $w))
            ->when($filter['tipe'] ?? null, fn ($q, $t) => $q->where('tipe', $t))
            ->when($filter['jenis'] ?? null, fn ($q, $j) => $q->where('jenis', $j))
            ->when($filter['cari'] ?? null, fn ($q, $c) => $q->where('nama', 'like', '%'.$c.'%'))
            ->orderBy('nama')
            ->paginate(50)
            ->withQueryString();

        return view('admin.file-arsip.index', [
            'pos' => $pos,
            'arsip' => ArsipPos::whereIn('pos_id', $pos->pluck('id'))->get()->groupBy('pos_id')->map(fn ($a) => $a->keyBy('tahun')),
            'daftarTahun' => range(now()->year, now()->year - self::RENTANG_TAHUN), // tahun ini s.d. 10 tahun lalu (11 kolom, seperti aplikasi lama)
            'filter' => $filter,
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
        ]);
    }

    /** Halaman kelola satu sel (pos, tahun): lihat, unggah/ganti, hapus, generate. */
    public function show(Request $request, Pos $pos, int $tahun): View
    {
        $this->pastikanBoleh($request, $pos);
        $this->pastikanTahun($tahun);

        return view('admin.file-arsip.show', [
            'pos' => $pos,
            'tahun' => $tahun,
            'arsip' => ArsipPos::where(['pos_id' => $pos->id, 'tahun' => $tahun])->first(),
            'bisaGenerate' => in_array($pos->jenis, [JenisPos::Arr, JenisPos::Awlr], true),
        ]);
    }

    public function unggah(Request $request, Pos $pos, int $tahun): RedirectResponse
    {
        $this->pastikanBoleh($request, $pos);
        $this->pastikanTahun($tahun);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:20480']], [], ['file' => 'file PDF']);

        $path = $request->file('file')->storeAs('file/arsip/'.$pos->id, $this->namaFile($pos, $tahun), 'public');
        $this->simpan($pos, $tahun, $path);

        return to_route('admin.arsip.show', [$pos, $tahun])->with('pesan', 'File arsip disimpan.');
    }

    /** Buat PDF buku data harian setahun dari pengamatan manual (aplikasi lama: Fpos::generate -> Cetak::arr/awlr). */
    public function generate(Request $request, Pos $pos, int $tahun): RedirectResponse
    {
        $this->pastikanBoleh($request, $pos);
        $this->pastikanTahun($tahun);
        abort_unless(in_array($pos->jenis, [JenisPos::Arr, JenisPos::Awlr], true), 404);

        $this->buatPdf($pos, $tahun);

        return to_route('admin.arsip.show', [$pos, $tahun])->with('pesan', 'PDF arsip '.$tahun.' dibuat.');
    }

    /** Buat (ulang) PDF buku data pos ARR/AWLR untuk satu tahun dan catat di arsip_pos. Juga dipakai InputController. */
    public function buatPdf(Pos $pos, int $tahun): void
    {
        $pdf = Pdf::loadView('admin.file-arsip.pdf', $this->bukuData($pos->load('walidata:id,nama'), $tahun))->setPaper('a4');
        $path = 'file/arsip/'.$pos->id.'/'.$this->namaFile($pos, $tahun);
        Storage::disk('public')->put($path, $pdf->output());
        $this->simpan($pos, $tahun, $path);
    }

    public function berkas(Request $request, ArsipPos $arsip): StreamedResponse
    {
        $this->pastikanBoleh($request, $arsip->pos);
        abort_unless(Storage::disk('public')->exists($arsip->path), 404, 'File arsip belum tersedia di server.');

        return Storage::disk('public')->response($arsip->path);
    }

    public function destroy(Request $request, ArsipPos $arsip): RedirectResponse
    {
        $this->pastikanBoleh($request, $arsip->pos);
        Storage::disk('public')->delete($arsip->path);
        $arsip->delete();

        return to_route('admin.arsip.show', [$arsip->pos_id, $arsip->tahun])->with('pesan', 'File arsip dihapus.');
    }

    /**
     * Nilai harian [bulan][tanggal] setahun + ringkasan, untuk template PDF.
     *
     * @return array<string, mixed>
     */
    private function bukuData(Pos $pos, int $tahun): array
    {
        $hujan = $pos->jenis === JenisPos::Arr;
        $model = $hujan ? PengamatanArr::class : PengamatanAwlr::class;
        $kolom = $hujan ? 'hujan' : 'limpas';

        $harian = $model::where('pos_id', $pos->id)->whereYear('waktu', $tahun)->whereNotNull($kolom)
            ->get(['waktu', $kolom])
            ->groupBy(fn ($p) => $p->waktu->format('n-j'))
            ->map(fn ($hari) => $hujan ? $hari->sum($kolom) : $hari->avg($kolom));

        $nilai = [];
        foreach ($harian as $kunci => $v) {
            [$bulan, $tanggal] = array_map('intval', explode('-', $kunci));
            $nilai[$bulan][$tanggal] = (float) $v;
        }

        return [
            'pos' => $pos,
            'tahun' => $tahun,
            'hujan' => $hujan,
            'nilai' => $nilai,
            'hariPerBulan' => collect(range(1, 12))->mapWithKeys(fn ($b) => [$b => Carbon::create($tahun, $b)->daysInMonth])->all(),
        ];
    }

    /**
     * Satu arsip per pos per tahun: baris pertama diperbarui, baris lain (mis. sisa impor yang dobel) dan file lama dihapus,
     * agar grid, halaman kelola, dan tautan unduh selalu menunjuk file yang sama.
     */
    private function simpan(Pos $pos, int $tahun, string $path): void
    {
        $semua = ArsipPos::where(['pos_id' => $pos->id, 'tahun' => $tahun])->orderBy('id')->get();
        $semua->filter(fn ($a) => $a->path !== $path)->each(fn ($a) => Storage::disk('public')->delete($a->path));
        $semua->slice(1)->each->delete();

        $arsip = $semua->first() ?? new ArsipPos(['pos_id' => $pos->id, 'tahun' => $tahun]);
        $arsip->fill(['path' => $path, 'ukuran' => Storage::disk('public')->size($path)])->save();
    }

    /** Tahun dari URL harus masuk akal (kolom YEAR MySQL 1901-2155); di luar itu 404, bukan galat database. */
    private function pastikanTahun(int $tahun): void
    {
        abort_unless($tahun >= 1950 && $tahun <= now()->year + 1, 404);
    }

    /** Pola nama file sama dengan aplikasi lama: {tahun}-{pos}-{slug}-{waktu}.pdf */
    private function namaFile(Pos $pos, int $tahun): string
    {
        return $tahun.'-'.$pos->id.'-'.Str::slug($pos->nama).'-'.now()->timestamp.'.pdf';
    }

    private function pastikanBoleh(Request $request, Pos $pos): void
    {
        abort_unless(Pos::terlihatOleh($request->user())->whereKey($pos->id)->exists(), 404);
    }
}

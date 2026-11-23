<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Buletin;
use App\Models\Dokumen;
use App\Models\Kategori;
use App\Models\Peta;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Menu Publikasi portal publik (pengganti info/publikasi, info/galeripeta, info/buletin, info/album lama).
 * Hanya konten yang terbit. Isi jendela detail (foto besar, PDF, video) dirender di halaman yang sama.
 */
class PublikasiController extends Controller
{
    /**
     * Kategori buletin di portal lama => [nilai kolom bidang (null = tanpa bidang), judul, keterangan, ikon].
     * Teks dari vbuletin2 lama.
     */
    public const BULETIN = [
        'hidrologi' => ['Hidrologi', 'Hidrologi', 'Ilmu yang mempelajari air di permukaan tanah, termasuk pergerakan, distribusi, kualitas, serta interaksinya dengan air hujan dan air tanah.', 'bi-droplet'],
        'hidrometeorologi' => ['Hidrometeorologi', 'Hidrometeorologi', 'Interaksi antara atmosfer dan siklus air, khususnya kaitannya dengan cuaca, iklim, serta dampaknya terhadap bencana alam.', 'bi-cloud-rain'],
        'hidrogeologi' => ['Hidrogeologi', 'Hidrogeologi', 'Cabang geologi tentang air tanah: keberadaan, pergerakan, kualitas, dan interaksinya dengan batuan serta tanah di bawah permukaan.', 'bi-globe-asia-australia'],
        'kejadian' => ['Kejadian', 'Kejadian Lainnya', 'Laporan kejadian terkait sumber daya air dan mitigasi bencana di Wilayah Sungai Serayu Opak.', 'bi-exclamation-triangle'],
        'elnino' => ['ElNino', 'El Nino', 'Laporan Satgas Antisipasi dan Penanganan Dampak El Nino.', 'bi-sun'],
        'lainnya' => [null, 'Lainnya', 'Buletin lain seputar hidrologi, hidrometeorologi, dan hidrogeologi untuk peringatan dini dan perencanaan tata air.', 'bi-folder2-open'],
    ];

    /**
     * Warna khas kategori buletin seperti portal live lama => [latar sampul, titik, teks]. Teks lolos kontras AA di putih.
     * Kelas ditulis utuh agar terbaca Tailwind.
     */
    public const WARNA_BULETIN = [
        'hidrologi' => ['bg-[#e8f2fd]', 'bg-[#0b63ce]', 'text-[#0b63ce]'],
        'hidrometeorologi' => ['bg-[#efe9fd]', 'bg-[#6d4fd1]', 'text-[#5b3fc0]'],
        'hidrogeologi' => ['bg-[#e3f6ec]', 'bg-[#1b8a5a]', 'text-[#17784e]'],
        'kejadian' => ['bg-[#fff3d6]', 'bg-[#c27a00]', 'text-[#935800]'],
        'elnino' => ['bg-[#fdebe4]', 'bg-[#d4500f]', 'text-[#b23c08]'],
        'lainnya' => ['bg-gray-100', 'bg-gray-500', 'text-gray-700'],
    ];

    /** Urutan sumber buletin, sama dengan arr_sumber_buletin lama. */
    public const SUMBER_BULETIN = ['BBWS Serayu Opak', 'Stasiun Klimatologi DI Yogyakarta', 'Stasiun Klimatologi Jawa Tengah'];

    /**
     * Dokumen & peraturan, tampilan sama dengan Informasi H3: pilihan jenis (?jenis=slug) + satu tabel, urut jenis lalu
     * terbaru. Semua tampil (lama: batas 100 per halaman tidak pernah tercapai).
     */
    public function dokumen(Request $request): View
    {
        $semua = Dokumen::with('kategori', 'lampiran')->where('terbit', true)->get()
            ->sortBy([fn ($a, $b) => ($a->kategori_id ?? PHP_INT_MAX) <=> ($b->kategori_id ?? PHP_INT_MAX), ['id', 'desc']])->values();
        $kategori = $semua->pluck('kategori')->filter()->unique('id')->sortBy('id')->values();
        $aktif = $kategori->firstWhere('slug', $request->query('jenis'));

        return view('portal.publikasi.dokumen', [
            'dokumen' => $aktif ? $semua->where('kategori_id', $aktif->id)->values() : $semua,
            'kategori' => $kategori,
            'aktif' => $aktif,
            'jumlahSemua' => $semua->count(),
            'jumlahPerJenis' => $semua->countBy('kategori_id'),
        ]);
    }

    /** Galeri peta: pilihan jenis + cari judul, 9 per halaman, terbaru dulu (seperti lama). */
    public function peta(Request $request): View
    {
        $kategori = Kategori::where('jenis', 'peta')->orderBy('urutan')->orderBy('id')->get();
        $aktif = $kategori->firstWhere('slug', $request->query('jenis'));

        $peta = Peta::with('kategori', 'lampiran')->where('terbit', true)
            ->when($aktif, fn ($q) => $q->where('kategori_id', $aktif->id))
            ->when($request->query('q'), fn ($q, $cari) => $q->where('nama', 'like', '%'.$cari.'%'))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(9)->withQueryString();

        return view('portal.publikasi.peta', compact('kategori', 'aktif', 'peta'));
    }

    /** Beranda buletin: kartu edisi terbaru tiap kategori + jumlah edisi (kategori tanpa edisi tetap tampil, seperti lama). */
    public function buletin(): View
    {
        $terbaru = collect(self::BULETIN)->map(fn ($k) => $this->kueriBuletin($k[0])->with('lampiran', 'kategori')->first());

        return view('portal.publikasi.buletin', [
            'terbaru' => $terbaru,
            'jumlah' => $this->jumlahPerKategori(),
        ]);
    }

    /**
     * Edisi satu kategori buletin, dikelompokkan per sumber seperti lama. Tanpa ?sumber tiap sumber menampilkan
     * 8 edisi terbaru + tautan "lihat semua"; dengan ?sumber tampil semua edisi sumber itu, 24 per halaman.
     */
    public function buletinKategori(string $slug, Request $request): View
    {
        abort_unless(isset(self::BULETIN[$slug]), 404);
        $bidang = self::BULETIN[$slug][0];
        $sumber = in_array($request->query('sumber'), self::SUMBER_BULETIN, true) ? $request->query('sumber') : null;

        $jumlah = $this->kueriBuletin($bidang)->reorder()->selectRaw('sumber, count(*) as n')->groupBy('sumber')->pluck('n', 'sumber');

        return view('portal.publikasi.buletin-kategori', [
            'slug' => $slug,
            'jumlahKategori' => $this->jumlahPerKategori(),
            'sumber' => $sumber,
            'jumlah' => $jumlah,
            'halaman' => $sumber ? $this->kueriBuletin($bidang)->with('lampiran', 'kategori')->where('sumber', $sumber)->paginate(24)->withQueryString() : null,
            'kelompok' => $sumber ? null : collect(self::SUMBER_BULETIN)->filter(fn ($s) => $jumlah[$s] ?? 0)
                ->mapWithKeys(fn ($s) => [$s => $this->kueriBuletin($bidang)->with('lampiran', 'kategori')->where('sumber', $s)->limit(8)->get()]),
        ]);
    }

    /** Album kegiatan: pilihan jenis (Acara/Lapangan), 9 per halaman, terbaru dulu. */
    public function album(Request $request): View
    {
        $jenis = in_array($request->query('jenis'), ['Acara', 'Lapangan'], true) ? $request->query('jenis') : null;

        $album = Album::with('lampiran')->where('terbit', true)
            ->when($jenis, fn ($q) => $q->where('jenis', $jenis))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(9)->withQueryString();

        return view('portal.publikasi.album', compact('jenis', 'album'));
    }

    /** Jumlah edisi terbit per slug kategori buletin. */
    private function jumlahPerKategori(): array
    {
        $perBidang = Buletin::where('terbit', true)->selectRaw('bidang, count(*) as n')->groupBy('bidang')->pluck('n', 'bidang');

        // Bidang null (kategori Lainnya) menjadi kunci '' pada pluck.
        return collect(self::BULETIN)->map(fn ($k) => (int) ($perBidang[$k[0] ?? ''] ?? 0))->all();
    }

    private function kueriBuletin(?string $bidang)
    {
        return Buletin::where('terbit', true)
            ->when($bidang, fn ($q) => $q->where('bidang', $bidang), fn ($q) => $q->whereNull('bidang'))
            ->orderByDesc('tanggal')->orderByDesc('id');
    }
}

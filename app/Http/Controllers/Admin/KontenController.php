<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\JenisPos;
use App\Enums\MetodePos;
use App\Enums\TipePos;
use App\Models\Album;
use App\Models\Buletin;
use App\Models\Dokumen;
use App\Models\Galeri;
use App\Models\Glosarium;
use App\Models\Kategori;
use App\Models\Kejadian;
use App\Models\Lampiran;
use App\Models\PengirimanAir;
use App\Models\Peta;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Konten portal: Publikasi (Galeri Pos, Galeri Peta, Glosarium, Buletin, Album, Publikasi Dokumen,
 * Penanganan Kekeringan) dan Catatan Kejadian. Asal: Galeri.php, Gpeta.php, Glosarium.php, Buletin.php,
 * Album.php, Buku.php, Pengiriman.php, Kejadian.php — semuanya memakai pola generik yang sama di aplikasi lama
 * (judul, keterangan, tanggal, tampil, lampiran bernomor), jadi di sini satu controller dengan konfigurasi per jenis.
 *
 * Konfigurasi JENIS:
 * - isian: kolom => [label, tipe, opsi?]; tipe: teks, area, tanggal, angka, url, pilihan (daftar, nilai => label, atau [Enum::class]),
 *   kategori (opsi = jenis kategori), pos, walidata, tampil (boolean; opsi = [teks ya, teks tidak]).
 *   Wajib: isian pertama, dan kolom yang di database NOT NULL tanpa nilai bawaan.
 * - urut/arah: urutan daftar (bawaan menurun); bawaan: nilai awal form tambah; induk: induk breadcrumb.
 * Juga dipakai untuk data master Walidata dan Pos (menu Pengaturan).
 * - tabel: kolom (atau walidata/pos/lampiran) yang tampil di daftar, label sama dengan aplikasi lama.
 * - lampiran: [label, jenis, urutan] — slot file sama dengan hasil impor (gambar 1-7, pdf 10, dokumen 11).
 * - pos: konten terikat pos (Galeri Pos, Catatan Kejadian) dibatasi pada pos pengguna; lainnya khusus superadmin.
 *
 * Catatan: jenis dibaca dari default route ($request->route('jenis')), bukan parameter method, karena Laravel
 * meneruskan parameter route ke method menurut URUTAN (default route tertukar dengan {id}).
 *
 * Perbedaan dengan aplikasi lama: form di halaman sendiri (dulu modal), file dibuka lewat route,
 * dan lampiran di daftar diringkas dalam satu kolom.
 */
class KontenController extends Controller
{
    private const TAMPIL = ['Tampil', 'tampil', ['Ya, tampil di portal', 'Tidak tampil']]; // [label, tipe, [teks ya, teks tidak]]

    public const JENIS = [
        'galeri' => [
            'judul' => 'Galeri Pos', 'model' => Galeri::class, 'pos' => true, 'urut' => 'tanggal',
            'isian' => ['nama' => ['Judul Galeri', 'teks'], 'pos_id' => ['POS', 'pos'], 'keterangan' => ['Keterangan', 'teks'], 'tanggal' => ['Tanggal', 'tanggal'], 'terbit' => self::TAMPIL],
            'tabel' => ['walidata' => 'Walidata', 'pos' => 'POS', 'nama' => 'Judul Galeri', 'tanggal' => 'Tanggal', 'terbit' => 'Tampil', 'lampiran' => 'Gambar'],
            'lampiran' => [['Gambar', 'gambar', 1]],
        ],
        'peta' => [
            'judul' => 'Galeri Peta', 'model' => Peta::class, 'urut' => 'tanggal',
            'isian' => ['nama' => ['Judul Galeri Peta', 'teks'], 'kategori_id' => ['Jenis', 'kategori', 'peta'], 'keterangan' => ['Keterangan', 'area'],
                'tanggal' => ['Tanggal', 'tanggal'], 'terbit' => self::TAMPIL, 'url' => ['URL', 'url'], 'sumber' => ['Sumber', 'teks']],
            'tabel' => ['nama' => 'Judul Galeri Peta', 'kategori' => 'Jenis', 'tanggal' => 'Tanggal', 'terbit' => 'Tampil', 'url' => 'URL', 'lampiran' => 'Gambar / PDF'],
            'lampiran' => [['Gambar 1', 'gambar', 1], ['File PDF', 'pdf', 10]],
        ],
        'glosarium' => [
            'judul' => 'Glosarium', 'model' => Glosarium::class, 'urut' => 'urutan',
            'isian' => ['istilah' => ['Judul', 'teks'], 'bidang' => ['Tipe', 'pilihan', ['Umum', 'Hidrologi', 'Hidrogeologi', 'Hidrometeorologi']],
                'keterangan' => ['Keterangan', 'area'], 'tanggal' => ['Tanggal', 'tanggal'], 'urutan' => ['Nomor Urut', 'angka'], 'terbit' => self::TAMPIL, 'url' => ['URL', 'url']],
            'tabel' => ['bidang' => 'Tipe', 'istilah' => 'Judul', 'urutan' => 'Nomor Urut', 'terbit' => 'Tampil', 'url' => 'URL'],
            'lampiran' => [['Gambar', 'gambar', 1]],
        ],
        'buletin' => [
            'judul' => 'Buletin', 'model' => Buletin::class, 'urut' => 'tanggal',
            'isian' => ['judul' => ['Judul', 'teks'], 'sumber' => ['Sumber', 'pilihan', ['BBWS Serayu Opak', 'Stasiun Klimatologi DI Yogyakarta', 'Stasiun Klimatologi Jawa Tengah']],
                'bidang' => ['Jenis', 'pilihan', ['Hidrologi', 'Hidrometeorologi', 'Hidrogeologi', 'Kejadian', 'ElNino']], 'kategori_id' => ['Kategori', 'kategori', 'buletin'],
                'ringkasan' => ['Keterangan', 'area'], 'tanggal' => ['Tanggal', 'tanggal'], 'terbit' => self::TAMPIL, 'url_sumber' => ['URL', 'url']],
            'tabel' => ['sumber' => 'Sumber', 'bidang' => 'Jenis', 'judul' => 'Judul', 'tanggal' => 'Tanggal', 'terbit' => 'Tampil', 'kategori' => 'Kategori', 'lampiran' => 'Gambar / PDF'],
            'lampiran' => [['Gambar 1', 'gambar', 1], ['Gambar 2', 'gambar', 2], ['Gambar 3', 'gambar', 3], ['File PDF', 'pdf', 10]],
        ],
        'album' => [
            'judul' => 'Album', 'model' => Album::class, 'urut' => 'tanggal',
            'isian' => ['nama' => ['Judul Album', 'teks'], 'jenis' => ['Jenis', 'pilihan', ['Lapangan', 'Acara']], 'keterangan' => ['Keterangan', 'area'],
                'tanggal' => ['Tanggal', 'tanggal'], 'terbit' => self::TAMPIL, 'url_video' => ['Tautan Video', 'url'], 'url' => ['URL', 'url']],
            'tabel' => ['nama' => 'Judul Album', 'jenis' => 'Jenis', 'tanggal' => 'Tanggal', 'terbit' => 'Tampil', 'url_video' => 'Tautan Video', 'lampiran' => 'Gambar / PDF'],
            'lampiran' => [['Gambar 1', 'gambar', 1], ['Gambar 2', 'gambar', 2], ['Gambar 3', 'gambar', 3], ['Gambar 4', 'gambar', 4],
                ['Gambar 5', 'gambar', 5], ['Gambar 6', 'gambar', 6], ['Gambar 7', 'gambar', 7], ['File PDF', 'pdf', 10]],
        ],
        'dokumen' => [
            'judul' => 'Publikasi Dokumen', 'model' => Dokumen::class, 'urut' => 'id',
            'isian' => ['nama' => ['Judul', 'teks'], 'kategori_id' => ['Jenis', 'kategori', 'buku'], 'tahun' => ['Tahun', 'teks'], 'isi' => ['Isi', 'area'], 'terbit' => self::TAMPIL],
            'tabel' => ['nama' => 'Judul', 'kategori' => 'Jenis', 'tahun' => 'Tahun', 'terbit' => 'Tampil', 'lampiran' => 'File'],
            'lampiran' => [['File', 'dokumen', 1]],
        ],
        'pengiriman-air' => [
            'judul' => 'Penanganan Kekeringan', 'model' => PengirimanAir::class, 'urut' => 'tanggal',
            'isian' => ['tanggal' => ['Tanggal', 'tanggal'], 'jumlah_terdampak' => ['Jumlah Terdampak (jiwa)', 'angka'], 'jumlah_tangki' => ['Jumlah Tangki', 'angka'],
                'volume' => ['Volume Air (liter)', 'angka'], 'penyedia' => ['Penyedia Jasa', 'teks'], 'fasilitas_umum' => ['Fasilitas Umum', 'teks'], 'kontak' => ['Contact Person', 'teks'],
                'desa' => ['Desa', 'teks'], 'kecamatan' => ['Kecamatan', 'teks'], 'kabupaten' => ['Kabupaten', 'teks'], 'provinsi' => ['Provinsi', 'teks'],
                'latitude' => ['Latitude', 'angka'], 'longitude' => ['Longitude', 'angka'], 'status' => ['Status', 'teks'], 'catatan' => ['Catatan', 'area']],
            'tabel' => ['tanggal' => 'Tanggal', 'desa' => 'Desa', 'kecamatan' => 'Kecamatan', 'kabupaten' => 'Kabupaten', 'jumlah_terdampak' => 'Jumlah Terdampak (jiwa)',
                'jumlah_tangki' => 'Jumlah Tangki', 'volume' => 'Volume Air (liter)', 'penyedia' => 'Penyedia Jasa', 'status' => 'Status', 'lampiran' => 'Gambar'],
            'lampiran' => [['Gambar 1', 'gambar', 1]],
        ],
        'kejadian' => [
            'judul' => 'Catatan Kejadian', 'model' => Kejadian::class, 'pos' => true, 'urut' => 'tanggal',
            'isian' => ['nama' => ['Judul Kejadian', 'teks'], 'pos_id' => ['POS', 'pos'], 'kategori_id' => ['Kategori', 'kategori', 'kejadian'],
                'keterangan' => ['Keterangan', 'teks'], 'tanggal' => ['Tanggal', 'tanggal']],
            'tabel' => ['walidata' => 'Walidata', 'pos' => 'POS', 'nama' => 'Judul Kejadian', 'kategori' => 'Kategori', 'keterangan' => 'Keterangan', 'tanggal' => 'Tanggal', 'lampiran' => 'Gambar'],
            'lampiran' => [['Gambar', 'gambar', 1]],
        ],
        // Data master (menu Pengaturan). Asal: Master.php (walidata, urut walidata_urut) dan Pos.php.
        'walidata' => [
            'judul' => 'Walidata', 'model' => Walidata::class, 'induk' => 'Pengaturan', 'urut' => 'urutan', 'arah' => 'asc', 'bawaan' => ['setujui_otomatis' => false],
            'isian' => ['nama' => ['Nama', 'teks'], 'nama_panjang' => ['Nama Panjang', 'teks'], 'urutan' => ['Nomor Urut', 'angka'],
                'website' => ['Website', 'url'], 'url_ppid' => ['URL PPID', 'url'], 'url_kontak' => ['URL Kontak', 'url'],
                'email' => ['Email', 'teks'], 'whatsapp' => ['WhatsApp', 'teks'], 'kontak' => ['Kontak', 'teks'],
                'setujui_otomatis' => ['Permintaan Data', 'tampil', ['Disetujui otomatis', 'Perlu verifikasi']]],
            'tabel' => ['urutan' => 'Nomor Urut', 'nama' => 'Nama', 'nama_panjang' => 'Nama Panjang', 'website' => 'Website', 'email' => 'Email', 'setujui_otomatis' => 'Setujui Otomatis'],
            'lampiran' => [],
        ],
        'pos' => [
            'judul' => 'Pos', 'model' => Pos::class, 'induk' => 'Pengaturan', 'urut' => 'nama', 'arah' => 'asc', 'bawaan' => ['aktif' => true],
            'isian' => ['nama' => ['Nama POS', 'teks'], 'walidata_id' => ['Walidata', 'walidata'],
                'tipe' => ['Tipe', 'pilihan', [TipePos::class]], 'jenis' => ['Jenis', 'pilihan', [JenisPos::class]], 'metode' => ['Metode', 'pilihan', [MetodePos::class]],
                'kode' => ['Kode', 'teks'], 'nomor_register' => ['Nomor Register', 'teks'], 'tahun_pembuatan' => ['Tahun Pembuatan', 'angka'],
                'latitude' => ['Latitude', 'angka'], 'longitude' => ['Longitude', 'angka'], 'elevasi' => ['Elevasi (m)', 'angka'],
                'wilayah_sungai' => ['Wilayah Sungai', 'teks'], 'das' => ['DAS', 'teks'], 'sungai' => ['Sungai', 'teks'], 'anak_sungai' => ['Anak Sungai', 'teks'],
                'provinsi' => ['Provinsi', 'teks'], 'kabupaten' => ['Kabupaten', 'teks'], 'kecamatan' => ['Kecamatan', 'teks'], 'desa' => ['Desa', 'teks'],
                'petugas_nama' => ['Nama Petugas', 'teks'], 'petugas_nip' => ['NIP Petugas', 'teks'], 'petugas_telepon' => ['Telepon Petugas', 'teks'],
                'data_tersedia_dari' => ['Data Tersedia Dari (tahun)', 'angka'], 'data_tersedia_sampai' => ['Data Tersedia Sampai (tahun)', 'angka'],
                'aktif' => ['Status', 'tampil', ['Aktif', 'Nonaktif']]],
            'tabel' => ['id' => 'POS ID', 'nama' => 'Nama POS', 'walidata' => 'Walidata', 'tipe' => 'Tipe', 'jenis' => 'Jenis', 'metode' => 'Metode', 'kabupaten' => 'Kabupaten', 'aktif' => 'Status'],
            'lampiran' => [],
        ],
    ];

    public function index(Request $request): View
    {
        $jenis = $request->route('jenis');
        $konten = $this->konten($request, $jenis);
        $cari = $request->validate(['cari' => ['nullable', 'string', 'max:100']])['cari'] ?? null;
        $kolomJudul = array_key_first($konten['isian']);

        $data = $this->query($request, $konten)
            ->with(array_filter([$konten['lampiran'] ? 'lampiran' : null, isset($konten['isian']['kategori_id']) ? 'kategori' : null,
                ! empty($konten['pos']) ? 'pos.walidata' : null, isset($konten['isian']['walidata_id']) ? 'walidata' : null]))
            ->when($cari, fn ($q) => $q->where($kolomJudul === 'tanggal' ? 'desa' : $kolomJudul, 'like', '%'.$cari.'%'))
            ->orderBy($konten['urut'], $konten['arah'] ?? 'desc')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('admin.konten.index', compact('data', 'jenis', 'konten', 'cari'));
    }

    public function create(Request $request): View
    {
        $jenis = $request->route('jenis');
        $konten = $this->konten($request, $jenis);
        $model = new $konten['model'];
        if (isset($konten['isian']['tanggal'])) {
            $model->tanggal = today();
        }
        foreach ($konten['isian'] as $kolom => $isian) {
            if ($isian[1] === 'tampil') {
                $model->$kolom = $konten['bawaan'][$kolom] ?? true;
            }
        }

        return $this->form($request, $jenis, $konten, $model);
    }

    public function store(Request $request): RedirectResponse
    {
        $jenis = $request->route('jenis');
        $konten = $this->konten($request, $jenis);
        $data = $this->validasi($request, $konten);

        DB::transaction(function () use ($request, $konten, $data) {
            $model = $konten['model']::create($data);
            $this->simpanLampiran($request, $konten, $model);
        });

        return to_route('admin.'.$jenis)->with('pesan', $konten['judul'].' disimpan.');
    }

    public function edit(Request $request, string $id): View
    {
        $jenis = $request->route('jenis');
        $konten = $this->konten($request, $jenis);

        return $this->form($request, $jenis, $konten, $this->temukan($request, $konten, $id));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $jenis = $request->route('jenis');
        $konten = $this->konten($request, $jenis);
        $model = $this->temukan($request, $konten, $id);
        $data = $this->validasi($request, $konten);

        DB::transaction(function () use ($request, $konten, $model, $data) {
            $model->update($data);
            $this->simpanLampiran($request, $konten, $model);
        });

        return to_route('admin.'.$jenis)->with('pesan', $konten['judul'].' diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $jenis = $request->route('jenis');
        $konten = $this->konten($request, $jenis);
        $model = $this->temukan($request, $konten, $id);

        try {
            DB::transaction(function () use ($konten, $model) {
                if ($konten['lampiran']) {
                    $model->lampiran->each(fn (Lampiran $l) => $this->hapusLampiran($l));
                }
                $model->delete();
            });
        } catch (QueryException) {
            // Mis. walidata yang masih punya pos atau pengguna (foreign key).
            return back()->with('pesan', $konten['judul'].' tidak bisa dihapus karena masih dipakai data lain.');
        }

        return to_route('admin.'.$jenis)->with('pesan', $konten['judul'].' dihapus.');
    }

    /** Buka file lampiran (gambar/PDF/dokumen) setelah memastikan konten pemiliknya boleh diakses. */
    public function lampiran(Request $request, Lampiran $lampiran): StreamedResponse
    {
        $jenis = collect(self::JENIS)->search(fn ($k) => (new $k['model'])->getMorphClass() === $lampiran->lampiran_type);
        abort_if($jenis === false, 404);
        $this->temukan($request, $this->konten($request, $jenis), $lampiran->lampiran_id);
        abort_unless(Storage::disk('public')->exists($lampiran->path), 404, 'File belum tersedia di server.');

        return Storage::disk('public')->response($lampiran->path);
    }

    private function form(Request $request, string $jenis, array $konten, Model $model): View
    {
        $pilihanKategori = collect($konten['isian'])->where(1, 'kategori')
            ->map(fn ($i) => Kategori::where('jenis', $i[2])->orderBy('urutan')->orderBy('nama')->pluck('nama', 'id'));

        return view('admin.konten.form', [
            'jenis' => $jenis,
            'konten' => $konten,
            'model' => $model,
            'kategori' => $pilihanKategori,
            'daftarPos' => ! empty($konten['pos']) ? Pos::terlihatOleh($request->user())->orderBy('nama')->pluck('nama', 'id') : collect(),
            'daftarWalidata' => isset($konten['isian']['walidata_id']) ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            'lampiranAda' => $model->exists && $konten['lampiran'] ? $model->lampiran->keyBy(fn ($l) => $l->jenis.'-'.$l->urutan) : collect(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, array $konten): array
    {
        $aturan = [];
        $kolomPertama = array_key_first($konten['isian']);
        $tabel = (new $konten['model'])->getTable();
        foreach ($konten['isian'] as $kolom => [$label, $tipe]) {
            // Wajib: isian pertama, kolom NOT NULL tanpa bawaan, dan pos bagi non-superadmin (agar kontennya tetap terlihat).
            $wajib = $kolom === $kolomPertama || ($tipe !== 'tampil' && $this->wajibDiDatabase($tabel, $kolom))
                || ($tipe === 'pos' && ! $request->user()->isSuperadmin());
            $aturan[$kolom] = array_merge([$wajib ? 'required' : 'nullable'], match ($tipe) {
                'teks' => ['string', 'max:255'],
                'area' => ['string', 'max:65000'],
                'tanggal' => ['date'],
                'angka' => ['numeric'],
                'url' => ['url', 'max:255'],
                'pilihan' => [Rule::in(array_keys(self::pilihan($konten['isian'][$kolom][2])))],
                'walidata' => [Rule::exists('walidata', 'id')],
                'kategori' => [Rule::exists('kategori', 'id')->where('jenis', $konten['isian'][$kolom][2])],
                'pos' => [Rule::in(Pos::terlihatOleh($request->user())->pluck('id'))],
                'tampil' => ['boolean'],
            });
        }
        foreach ($konten['lampiran'] as [$label, $jenisFile, $urutan]) {
            $aturan["file.$jenisFile-$urutan"] = ['nullable', 'file', 'max:20480', match ($jenisFile) {
                'gambar' => 'mimes:jpg,jpeg,png,gif,webp',
                'pdf' => 'mimes:pdf',
                default => 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip',
            }];
            $aturan["hapus.$jenisFile-$urutan"] = ['nullable', 'boolean'];
        }

        $label = collect($konten['isian'])->map(fn ($i) => strtolower($i[0]))->all()
            + collect($konten['lampiran'])->mapWithKeys(fn ($l) => ["file.{$l[1]}-{$l[2]}" => strtolower($l[0])])->all();
        $data = $request->validate($aturan, ['pos_id.in' => 'Pos tidak ditemukan atau bukan pos Anda.'], $label);
        foreach ($konten['isian'] as $kolom => $isian) {
            if ($isian[1] === 'tampil') {
                $data[$kolom] = $request->boolean($kolom);
            }
        }

        return collect($data)->except(['file', 'hapus'])->all();
    }

    /** Unggah/ganti/hapus lampiran per slot (jenis + urutan). */
    private function simpanLampiran(Request $request, array $konten, Model $model): void
    {
        if (! $konten['lampiran']) {
            return; // data master (walidata, pos) tanpa lampiran
        }
        $ada = $model->lampiran()->get()->keyBy(fn ($l) => $l->jenis.'-'.$l->urutan);
        foreach ($konten['lampiran'] as [$label, $jenisFile, $urutan]) {
            $slot = "$jenisFile-$urutan";
            $file = $request->file("file.$slot");
            if (($file || $request->boolean("hapus.$slot")) && $ada->has($slot)) {
                $this->hapusLampiran($ada[$slot]);
            }
            if ($file) {
                $folder = 'file/'.$model->getMorphClass().'/'.now()->format('Y');
                $nama = $model->id.'-'.$slot.'-'.Str::random(8).'.'.strtolower($file->getClientOriginalExtension());
                $model->lampiran()->create([
                    'jenis' => $jenisFile,
                    'path' => $file->storeAs($folder, $nama, 'public'),
                    'ekstensi' => strtolower($file->getClientOriginalExtension()),
                    'ukuran' => $file->getSize(),
                    'urutan' => $urutan,
                ]);
            }
        }
    }

    /**
     * Hapus baris lampiran sekarang, filenya baru setelah transaksi berhasil: bila transaksi dibatalkan
     * (mis. konten gagal dihapus karena foreign key), baris kembali dan filenya masih ada.
     */
    private function hapusLampiran(Lampiran $lampiran): void
    {
        $file = array_filter([$lampiran->path, $lampiran->path_kecil]);
        $lampiran->delete();
        DB::afterCommit(fn () => Storage::disk('public')->delete($file));
    }

    private function konten(Request $request, string $jenis): array
    {
        $konten = self::JENIS[$jenis] ?? abort(404);
        abort_unless(! empty($konten['pos']) || $request->user()->isSuperadmin(), 403);

        return $konten;
    }

    private function query(Request $request, array $konten)
    {
        return $konten['model']::query()
            // Superadmin melihat semua (termasuk konten tanpa pos); peran lain hanya konten pos miliknya.
            ->when(! empty($konten['pos']) && ! $request->user()->isSuperadmin(), fn ($q) => $q->whereIn('pos_id', Pos::terlihatOleh($request->user())->select('id')));
    }

    private function temukan(Request $request, array $konten, int|string $id): Model
    {
        return $this->query($request, $konten)->findOrFail((int) $id);
    }

    /** Kolom NOT NULL tanpa nilai bawaan di database. */
    private function wajibDiDatabase(string $tabel, string $kolom): bool
    {
        $info = collect(DB::getSchemaBuilder()->getColumns($tabel))->firstWhere('name', $kolom);

        return $info && ! $info['nullable'] && $info['default'] === null;
    }

    /**
     * Pilihan isian "pilihan" sebagai nilai => label. Opsi boleh berupa daftar nilai, nilai => label, atau [KelasEnum::class].
     *
     * @return array<string, string>
     */
    public static function pilihan(array $opsi): array
    {
        if (count($opsi) === 1 && is_string($opsi[0] ?? null) && enum_exists($opsi[0])) {
            return collect($opsi[0]::cases())->mapWithKeys(fn ($e) => [$e->value => method_exists($e, 'label') ? $e->label() : $e->name])->all();
        }

        return array_is_list($opsi) ? array_combine($opsi, $opsi) : $opsi;
    }
}

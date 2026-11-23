<?php

use App\Models\Album;
use App\Models\Buletin;
use App\Models\Dokumen;
use App\Models\Kategori;
use App\Models\Peta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('public'));

function lampiran($model, string $jenis, string $path, int $urutan = 1): void
{
    $model->lampiran()->create(['jenis' => $jenis, 'path' => $path, 'urutan' => $urutan]);
    Storage::disk('public')->put($path, 'x');
}

it('dokumen: hanya yang terbit, satu tabel dengan pilihan jenis, tautan unduh', function () {
    $uu = Kategori::create(['jenis' => 'buku', 'nama' => 'Peraturan Perundang-undangan', 'slug' => 'uu']);
    $sop = Kategori::create(['jenis' => 'buku', 'nama' => 'Dokumen Non-Peraturan', 'slug' => 'non-peraturan']);
    $d = Dokumen::create(['kategori_id' => $uu->id, 'nama' => 'UU 17 Tahun 2019', 'tahun' => '2019', 'terbit' => true]);
    lampiran($d, 'dokumen', 'file/buku/uu.pdf');
    Dokumen::create(['kategori_id' => $sop->id, 'nama' => 'Prosedur Debit Banjir', 'terbit' => true]);
    Dokumen::create(['kategori_id' => $uu->id, 'nama' => 'Draf Rahasia', 'terbit' => false]);

    $this->get('/publikasi/dokumen')->assertOk()
        ->assertSeeInOrder(['Semua', '2', 'Peraturan Perundang-undangan', '1', 'Dokumen Non-Peraturan', '1'])
        ->assertSeeInOrder(['1.', 'UU 17 Tahun 2019', '2019', '2.', 'Prosedur Debit Banjir'])
        ->assertSee('/storage/file/buku/uu.pdf')
        ->assertDontSee('Draf Rahasia');
    $this->get('/publikasi/dokumen?jenis=non-peraturan')->assertSee('Prosedur Debit Banjir')->assertDontSee('UU 17 Tahun 2019');
});

it('peta: filter jenis dan cari judul', function () {
    $hid = Kategori::create(['jenis' => 'peta', 'nama' => 'Peta Hidrologi', 'slug' => 'peta-hidrologi']);
    $cat = Kategori::create(['jenis' => 'peta', 'nama' => 'Peta Cekungan Air Tanah', 'slug' => 'peta-cekungan-air-tanah']);
    $p = Peta::create(['kategori_id' => $hid->id, 'nama' => 'Peta Pos Hidrologi 2025', 'terbit' => true, 'tanggal' => '2025-08-22']);
    lampiran($p, 'gambar', 'file/gpeta/1.jpg');
    Peta::create(['kategori_id' => $cat->id, 'nama' => 'Peta CAT Yogyakarta', 'terbit' => true, 'tanggal' => '2025-01-01']);

    $this->get('/publikasi/peta')->assertOk()->assertSee('Peta Pos Hidrologi 2025')->assertSee('Peta CAT Yogyakarta')
        ->assertSee('Peta kondisi hidrologi dan potensi kekeringan'); // keterangan pengganti per jenis
    $this->get('/publikasi/peta?jenis=peta-cekungan-air-tanah')->assertSee('Peta CAT Yogyakarta')->assertDontSee('Peta Pos Hidrologi 2025');
    $this->get('/publikasi/peta?q=yogya')->assertSee('Peta CAT Yogyakarta')->assertDontSee('Peta Pos Hidrologi 2025');
});

it('buletin: edisi terbaru per kategori, lalu edisi per sumber', function () {
    Buletin::create(['bidang' => 'Hidrologi', 'tipe' => 'manual', 'judul' => 'Ketersediaan Air Oktober', 'sumber' => 'BBWS Serayu Opak', 'terbit' => true, 'tanggal' => '2026-10-01']);
    $baru = Buletin::create(['bidang' => 'Hidrologi', 'tipe' => 'manual', 'judul' => 'Ketersediaan Air November', 'sumber' => 'BBWS Serayu Opak', 'terbit' => true, 'tanggal' => '2026-11-01']);
    lampiran($baru, 'gambar', 'file/buletin/manual/nov.png');
    Buletin::create(['bidang' => null, 'tipe' => 'otomatis', 'judul' => 'Prakiraan Hujan Dasarian', 'sumber' => 'Stasiun Klimatologi DI Yogyakarta', 'terbit' => true, 'tanggal' => '2026-11-02']);
    Buletin::create(['bidang' => 'Hidrologi', 'tipe' => 'manual', 'judul' => 'Belum Terbit', 'sumber' => 'BBWS Serayu Opak', 'terbit' => false, 'tanggal' => '2026-11-20']);

    $this->get('/publikasi/buletin')->assertOk()
        ->assertSee('Ketersediaan Air November')->assertDontSee('Ketersediaan Air Oktober')
        ->assertSee('Prakiraan Hujan Dasarian')
        ->assertSee('Belum ada edisi'); // Hidrogeologi kosong tetap tampil

    $this->get('/publikasi/buletin/hidrologi')->assertOk()
        ->assertSeeInOrder(['BBWS Serayu Opak', 'Ketersediaan Air November', 'Ketersediaan Air Oktober'])
        ->assertSee('/storage/file/buletin/manual/nov.png')
        ->assertDontSee('Prakiraan Hujan Dasarian')->assertDontSee('Belum Terbit');
    $this->get('/publikasi/buletin/lainnya')->assertSee('Prakiraan Hujan Dasarian')->assertDontSee('Ketersediaan Air November');
    $this->get('/publikasi/buletin/hidrologi?sumber=BBWS+Serayu+Opak')->assertOk()->assertSee('Kembali ke semua sumber');
    $this->get('/publikasi/buletin/tidak-ada')->assertNotFound();
});

it('album: filter jenis, semua foto dan video YouTube di jendela', function () {
    $a = Album::create(['jenis' => 'Lapangan', 'nama' => 'Pengukuran Debit Ledok Sambi', 'terbit' => true, 'tanggal' => '2026-06-26',
        'url_video' => 'https://www.youtube.com/watch?v=abcDEF12345&t=3']);
    lampiran($a, 'gambar', 'file/album/9/1.png', 1);
    lampiran($a, 'gambar', 'file/album/9/2.png', 2);
    Album::create(['jenis' => 'Acara', 'nama' => 'Rapat Koordinasi', 'terbit' => true, 'tanggal' => '2026-05-01']);
    Album::create(['jenis' => 'Acara', 'nama' => 'Album Draf', 'terbit' => false, 'tanggal' => '2026-07-01']);

    $this->get('/publikasi/album')->assertOk()
        ->assertSeeInOrder(['Pengukuran Debit Ledok Sambi', 'Rapat Koordinasi'])
        ->assertSee('/storage/file/album/9/2.png')
        ->assertSee('youtube-nocookie.com/embed/abcDEF12345', false)
        ->assertDontSee('Album Draf');
    $this->get('/publikasi/album?jenis=Acara')->assertSee('Rapat Koordinasi')->assertDontSee('Pengukuran Debit Ledok Sambi');
});

it('menu Publikasi ada di header portal', function () {
    $this->get('/')->assertSee(route('portal.buletin'))->assertSee(route('portal.album'));
});

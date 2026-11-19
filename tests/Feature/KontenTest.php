<?php

use App\Http\Controllers\Admin\KontenController;
use App\Models\Album;
use App\Models\Buletin;
use App\Models\Kategori;
use App\Models\Kejadian;
use App\Models\Lampiran;
use App\Models\PengirimanAir;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 11, 'walidata_id' => 11, 'nama' => 'Bendung PJT', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

it('setiap jenis konten bisa dibuka superadmin: daftar dan form tambah', function () {
    $this->actingAs(akun('superadmin'));

    foreach (KontenController::JENIS as $jenis => $konten) {
        $halaman = $this->get('/admin/'.$jenis)->assertOk()->assertSee($konten['judul']);
        if ($konten['model']::count() === 0) {
            $halaman->assertSee('Belum ada data');
        }
        $this->get('/admin/'.$jenis.'/tambah')->assertOk()->assertSee('Tambah '.$konten['judul']);
        $this->get('/admin')->assertSee(route('admin.'.$jenis));
    }
});

it('publikasi khusus superadmin; catatan kejadian untuk semua peran', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get('/admin/buletin')->assertForbidden();
    $this->post('/admin/album', ['nama' => 'X', 'tanggal' => '2026-01-01'])->assertForbidden();
    $this->get('/admin/kejadian')->assertOk();
});

it('menambah album dengan gambar dan PDF, label kolom seperti aplikasi lama', function () {
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/album', [
        'nama' => 'Survei lapangan Progo', 'jenis' => 'Lapangan', 'tanggal' => '2026-10-01', 'terbit' => '1', 'url_video' => 'https://youtu.be/abc',
        'file' => ['gambar-1' => UploadedFile::fake()->image('a.jpg'), 'gambar-3' => UploadedFile::fake()->image('c.png'), 'pdf-10' => UploadedFile::fake()->createWithContent('l.pdf', '%PDF-1.4')],
    ])->assertRedirect('/admin/album');

    $album = Album::sole();
    expect($album->terbit)->toBeTrue()
        ->and($album->lampiran()->orderBy('urutan')->pluck('urutan')->all())->toBe([1, 3, 10])
        ->and($album->lampiran()->where('urutan', 10)->value('jenis'))->toBe('pdf');
    Storage::disk('public')->assertExists($album->lampiran->first()->path);

    $this->get('/admin/album')->assertOk()
        ->assertSeeInOrder(['Judul Album', 'Jenis', 'Tanggal', 'Tampil', 'Tautan Video', 'Gambar / PDF'])
        ->assertSeeInOrder(['Survei lapangan Progo', 'Lapangan', '1 Oktober 2026', 'Ya', 'https://youtu.be/abc']);
});

it('mengganti dan menghapus lampiran per slot, lalu menghapus konten beserta filenya', function () {
    $this->actingAs(akun('superadmin'));
    $this->post('/admin/album', ['nama' => 'A', 'tanggal' => '2026-10-01', 'file' => ['gambar-1' => UploadedFile::fake()->image('a.jpg'), 'gambar-2' => UploadedFile::fake()->image('b.jpg')]]);
    $album = Album::sole();
    [$g1, $g2] = $album->lampiran()->orderBy('urutan')->get()->all();

    $this->put("/admin/album/{$album->id}", ['nama' => 'A2', 'tanggal' => '2026-10-02', 'file' => ['gambar-1' => UploadedFile::fake()->image('baru.jpg')], 'hapus' => ['gambar-2' => '1']])
        ->assertRedirect('/admin/album');

    $sekarang = $album->lampiran()->get();
    expect($album->fresh()->nama)->toBe('A2')
        ->and($album->fresh()->terbit)->toBeFalse() // kotak Tampil tidak dicentang
        ->and($sekarang->pluck('urutan')->all())->toBe([1])
        ->and($sekarang->first()->id)->not->toBe($g1->id);
    Storage::disk('public')->assertMissing($g1->path);
    Storage::disk('public')->assertMissing($g2->path);

    $this->get("/admin/album/{$album->id}/ubah")->assertOk()->assertSee('Ubah Album')->assertSee(route('admin.konten.lampiran', $sekarang->first()));
    $this->get(route('admin.konten.lampiran', $sekarang->first()))->assertOk();

    $this->delete("/admin/album/{$album->id}")->assertRedirect('/admin/album');
    expect(Album::count())->toBe(0)->and(Lampiran::count())->toBe(0);
    Storage::disk('public')->assertMissing($sekarang->first()->path);
});

it('validasi: judul wajib, pilihan dan kategori harus sesuai, jenis file sesuai slot', function () {
    $this->actingAs(akun('superadmin'));
    $kategoriPeta = Kategori::create(['jenis' => 'peta', 'nama' => 'Peta Hidrologi', 'slug' => 'peta-hidrologi']);

    $this->post('/admin/buletin', ['judul' => '', 'tanggal' => ''])->assertSessionHasErrors(['judul', 'tanggal']);
    $this->post('/admin/buletin', ['judul' => 'B', 'tanggal' => '2026-01-01', 'sumber' => 'Lain', 'kategori_id' => $kategoriPeta->id])->assertSessionHasErrors(['sumber', 'kategori_id']);
    $this->post('/admin/album', ['nama' => 'A', 'tanggal' => '2026-01-01', 'file' => ['pdf-10' => UploadedFile::fake()->image('bukan.jpg')]])->assertSessionHasErrors('file.pdf-10');
    expect(Buletin::count())->toBe(0);

    $this->post('/admin/peta', ['nama' => 'Peta CAT', 'kategori_id' => $kategoriPeta->id])->assertRedirect('/admin/peta');
    $this->get('/admin/peta')->assertSeeInOrder(['Peta CAT', 'Peta Hidrologi']);
});

it('catatan kejadian dibatasi pada pos pengguna dan pos wajib bagi admin walidata', function () {
    $kejadianLain = Kejadian::create(['pos_id' => 11, 'nama' => 'Banjir PJT', 'tanggal' => '2026-01-01']);
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->post('/admin/kejadian', ['nama' => 'Tanpa pos', 'tanggal' => '2026-02-01'])->assertSessionHasErrors('pos_id');
    $this->post('/admin/kejadian', ['nama' => 'Pos lain', 'tanggal' => '2026-02-01', 'pos_id' => 11])->assertSessionHasErrors('pos_id');
    $this->post('/admin/kejadian', ['nama' => 'Pintu air macet', 'tanggal' => '2026-02-01', 'pos_id' => 10])->assertRedirect('/admin/kejadian');

    $this->get('/admin/kejadian')->assertOk()
        ->assertSeeInOrder(['Walidata', 'POS', 'Judul Kejadian', 'Kategori', 'Keterangan', 'Tanggal', 'Gambar'])
        ->assertSeeInOrder(['BBWS SERAYU OPAK', 'Bendung Glapan', 'Pintu air macet'])
        ->assertDontSee('Banjir PJT');
    $this->get("/admin/kejadian/{$kejadianLain->id}/ubah")->assertNotFound();
    $this->delete("/admin/kejadian/{$kejadianLain->id}")->assertNotFound();
});

it('penanganan kekeringan tanpa kolom tampil; angka dan lokasi tersimpan', function () {
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/pengiriman-air', ['tanggal' => '2026-08-01', 'desa' => 'Girisuko', 'kabupaten' => 'Gunungkidul', 'jumlah_terdampak' => '120', 'volume' => '5000', 'latitude' => '-7.9', 'status' => 'Terkirim'])
        ->assertRedirect('/admin/pengiriman-air');

    expect(PengirimanAir::sole())->desa->toBe('Girisuko')->jumlah_terdampak->toBe(120)->latitude->toBe(-7.9);
    $this->get('/admin/pengiriman-air')->assertSeeInOrder(['1 Agustus 2026', 'Girisuko', 'Gunungkidul', '120', '5.000', 'Terkirim'])->assertDontSee('Tampil');
});

it('data master walidata dan pos: khusus superadmin, enum dan status tampil benar', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/pos')->assertForbidden();
    $this->actingAs(akun('superadmin', ['username' => 'su']));

    $this->get('/admin/pos')->assertOk()
        ->assertSeeInOrder(['POS ID', 'Nama POS', 'Walidata', 'Tipe', 'Jenis', 'Metode', 'Kabupaten', 'Status'])
        ->assertSeeInOrder(['Bendung Glapan', 'BBWS SERAYU OPAK', 'Hidrologi', 'AWLR', 'Manual']);

    $this->post('/admin/pos', ['nama' => 'Pos Baru', 'walidata_id' => 1, 'tipe' => 'hidrologi', 'jenis' => 'kualitas_air', 'metode' => 'manual', 'tahun_pembuatan' => '2020', 'aktif' => '1'])
        ->assertRedirect('/admin/pos');
    $baru = Pos::where('nama', 'Pos Baru')->sole();
    expect($baru->jenis->value)->toBe('kualitas_air')->and($baru->aktif)->toBeTrue();

    $this->get("/admin/pos/{$baru->id}/ubah")->assertOk()->assertSee('<option value="kualitas_air" selected', false);
    $this->post('/admin/pos', ['nama' => 'Tanpa Tipe', 'walidata_id' => 1, 'jenis' => 'bukan'])->assertSessionHasErrors(['tipe', 'jenis']); // metode punya bawaan 'manual' di database

    $this->delete("/admin/pos/{$baru->id}")->assertRedirect();
    expect(Pos::withTrashed()->find($baru->id)->trashed())->toBeTrue(); // pos dihapus lunak

    // Walidata yang masih punya pos tidak bisa dihapus.
    $this->from('/admin/walidata')->delete('/admin/walidata/1')->assertRedirect('/admin/walidata')->assertSessionHas('pesan', 'Walidata tidak bisa dihapus karena masih dipakai data lain.');
    $this->post('/admin/walidata', ['nama' => 'DINAS BARU', 'urutan' => '20', 'setujui_otomatis' => '1'])->assertRedirect('/admin/walidata');
    $this->get('/admin/walidata')->assertSeeInOrder(['Nomor Urut', 'Nama', 'BBWS SERAYU OPAK', 'DINAS BARU', 'Ya']);
});

it('file lampiran tidak terhapus bila transaksi dibatalkan', function () {
    $this->actingAs(akun('superadmin'));
    $this->post('/admin/album', ['nama' => 'A', 'tanggal' => '2026-10-01', 'file' => ['gambar-1' => UploadedFile::fake()->image('a.jpg')]]);
    $lampiran = Lampiran::sole();
    $hapus = new ReflectionMethod(KontenController::class, 'hapusLampiran');

    try {
        DB::transaction(function () use ($hapus, $lampiran) {
            $hapus->invoke(app(KontenController::class), $lampiran);
            throw new RuntimeException('gagal di tengah transaksi');
        });
    } catch (RuntimeException) {
    }

    expect(Lampiran::count())->toBe(1);
    Storage::disk('public')->assertExists($lampiran->path);
});


<?php

use App\Models\PengamatanAwlr;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 11, 'walidata_id' => 11, 'nama' => 'Bendung PJT', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
});

function dugaAir(int $posId, string $waktu, float $tma = 1.2): PengamatanAwlr
{
    return PengamatanAwlr::create(['pos_id' => $posId, 'waktu' => $waktu, 'tma' => $tma]);
}

it('admin walidata hanya melihat data duga air walidatanya', function () {
    dugaAir(10, '2026-11-01 07:00:00', 1.234);
    dugaAir(11, '2026-11-01 07:00:00', 9.876);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/duga-air')
        ->assertOk()
        ->assertSee('1,23')
        ->assertDontSee('9,88');
});

it('menyaring menurut pos dan bulan', function () {
    dugaAir(10, '2026-10-31 17:00:00', 1.11);
    dugaAir(10, '2026-11-01 07:00:00', 2.22);
    dugaAir(11, '2026-11-01 07:00:00', 3.33);

    $this->actingAs(akun('superadmin'))->get('/admin/duga-air?pos=10&bulan=2026-11')
        ->assertOk()
        ->assertSee('2,22')
        ->assertDontSee('1,11')
        ->assertDontSee('3,33');
});

it('menyaring menurut walidata, nama pos, dan jam', function () {
    dugaAir(10, '2026-11-01 07:00:00', 1.11);
    dugaAir(10, '2026-11-01 12:00:00', 2.22);
    dugaAir(11, '2026-11-01 07:00:00', 3.33);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/duga-air?walidata=11')->assertOk()->assertSee('3,33')->assertDontSee('1,11')->assertDontSee('2,22');
    $this->get('/admin/duga-air?cari=glap')->assertOk()->assertSee('1,11')->assertSee('2,22')->assertDontSee('3,33');
    $this->get('/admin/duga-air?jam=12')->assertOk()->assertSee('2,22')->assertDontSee('1,11')->assertDontSee('3,33');
    $this->get('/admin/duga-air?jam=9')->assertSessionHasErrors('jam');
});

it('filter walidata tidak membuka data walidata lain bagi admin walidata', function () {
    dugaAir(11, '2026-11-01 07:00:00', 3.33);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/duga-air?walidata=11')
        ->assertOk()
        ->assertDontSee('3,33')
        ->assertDontSee('Semua walidata');
});

it('superadmin menambah data; pos non-AWLR dan duplikat jam ditolak', function () {
    $this->actingAs($admin = akun('superadmin'));
    $isi = ['pos_id' => 10, 'tanggal' => '2026-11-01', 'jam' => 12, 'tma' => '1.5', 'keterangan' => 'Normal'];

    $this->post('/admin/duga-air', $isi)->assertRedirect('/admin/duga-air');
    $data = PengamatanAwlr::sole();
    expect($data->waktu->toDateTimeString())->toBe('2026-11-01 12:00:00')
        ->and($data->tma)->toBe(1.5)
        ->and($data->diinput_oleh)->toBe($admin->id);

    $this->post('/admin/duga-air', $isi)->assertSessionHasErrors('jam');
    $this->post('/admin/duga-air', ['pos_id' => 20] + $isi)->assertSessionHasErrors('pos_id');
    $this->post('/admin/duga-air', ['jam' => 9] + $isi)->assertSessionHasErrors('jam');
});

it('petugas mencatat untuk posnya sendiri pada hari ini', function () {
    $this->actingAs(akun('petugas_pos', ['pos_id' => 10]));

    $this->post('/admin/duga-air', ['pos_id' => 11, 'tanggal' => '2020-01-01', 'jam' => 7, 'tma' => '0.8'])->assertRedirect();

    $data = PengamatanAwlr::sole();
    expect($data->pos_id)->toBe(10)
        ->and($data->waktu->toDateTimeString())->toBe(today()->setHour(7)->toDateTimeString());
});

it('tidak bisa mengubah atau menghapus data pos lain', function () {
    $lain = dugaAir(11, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/duga-air/{$lain->id}/ubah")->assertNotFound();
    $this->put("/admin/duga-air/{$lain->id}", ['pos_id' => 10, 'tanggal' => '2026-11-01', 'jam' => 7])->assertNotFound();
    $this->delete("/admin/duga-air/{$lain->id}")->assertNotFound();
    expect($lain->fresh())->not->toBeNull();
});

it('mengubah, mencabut validasi, dan menghapus data', function () {
    $data = dugaAir(10, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/duga-air/{$data->id}/ubah")->assertOk()->assertSeeInOrder(['Duga Air', 'Ubah data', 'Bendung Glapan', '2026-11-01', 'Tervalidasi', 'Hapus', 'Simpan perubahan']);
    $this->put("/admin/duga-air/{$data->id}", ['pos_id' => 10, 'tanggal' => '2026-11-01', 'jam' => 7, 'tma' => '2.75'])->assertRedirect();
    expect($data->fresh())->tma->toBe(2.75)->tervalidasi->toBeFalse();

    $this->put("/admin/duga-air/{$data->id}", ['pos_id' => 10, 'tanggal' => '2026-11-01', 'jam' => 7, 'tervalidasi' => '1'])->assertRedirect();
    expect($data->fresh()->tervalidasi)->toBeTrue();

    $this->delete("/admin/duga-air/{$data->id}")->assertRedirect();
    expect($data->fresh())->toBeNull();
});

it('petugas tidak bisa mengubah status validasi', function () {
    $data = dugaAir(10, '2026-11-01 07:00:00');

    $this->actingAs(akun('petugas_pos', ['pos_id' => 10]))
        ->put("/admin/duga-air/{$data->id}", ['tanggal' => '2026-11-01', 'jam' => 7])
        ->assertRedirect();
    expect($data->fresh()->tervalidasi)->toBeTrue();
});

it('menu Duga Air di sidebar menjadi tautan', function () {
    $this->actingAs(akun('superadmin'))->get('/admin')->assertSee(route('admin.duga-air'));
});

it('form tambah menampilkan keadaan kosong bila admin tidak punya pos AWLR', function () {
    DB::table('walidata')->insert(['id' => 2, 'nama' => 'DINAS TANPA POS']);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 2]))->get('/admin/duga-air/tambah')
        ->assertOk()
        ->assertSee('Belum ada pos AWLR yang bisa Anda kelola')
        ->assertSee('data-simpan disabled', false);
});

it('form tambah menandai isian wajib dan menampilkan ringkasan galat', function () {
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/duga-air/tambah')->assertOk()->assertSee('Tambah Data Duga Air');
    $this->from('/admin/duga-air/tambah')->post('/admin/duga-air', ['tanggal' => '2026-11-01', 'jam' => 7, 'tma' => 'abc'])
        ->assertRedirect('/admin/duga-air/tambah');
    $this->get('/admin/duga-air/tambah')->assertSee('Data belum tersimpan. Perbaiki 2 isian yang ditandai');
});


it('tombol hapus memakai modal konfirmasi, bukan confirm() bawaan', function () {
    $data = dugaAir(10, '2026-11-01 07:00:00');
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/duga-air')->assertOk()
        ->assertSee('id="konfirmasi-hapus"', false)
        ->assertSee('data-konfirmasi="Data duga air Bendung Glapan, 1 November 2026 pukul 07.00, akan dihapus."', false)
        ->assertDontSee('confirm(', false);
    $this->get("/admin/duga-air/{$data->id}/ubah")->assertOk()->assertSee('data-konfirmasi="Data duga air Bendung Glapan', false);
});

it('pagination menampilkan rentang data dan nomor halaman', function () {
    foreach (range(1, 60) as $i) {
        dugaAir(10, now()->subDays($i)->setHour(7)->toDateTimeString());
    }
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/duga-air?page=2')->assertOk()
        ->assertSee('Menampilkan', false)
        ->assertSeeInOrder(['51–60', '60'])
        ->assertSee('aria-current="page"><span class="sr-only">Halaman </span>2', false)
        ->assertSee('rel="prev"', false)
        ->assertDontSee('rel="next"', false);
});

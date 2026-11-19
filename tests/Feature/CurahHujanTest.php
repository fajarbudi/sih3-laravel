<?php

use App\Models\PengamatanArr;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 21, 'walidata_id' => 11, 'nama' => 'ARR PJT', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

function curahHujan(int $posId, string $waktu, float $hujan = 12.5): PengamatanArr
{
    return PengamatanArr::create(['pos_id' => $posId, 'waktu' => $waktu, 'hujan' => $hujan]);
}

it('admin walidata hanya melihat data curah hujan walidatanya', function () {
    curahHujan(20, '2026-11-01 07:00:00', 12.34);
    curahHujan(21, '2026-11-01 07:00:00', 98.76);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/curah-hujan')
        ->assertOk()
        ->assertSee('12,34')
        ->assertDontSee('98,76');
});

it('menyaring menurut pos, jam, dan bulan', function () {
    curahHujan(20, '2026-10-31 17:00:00', 1.11);
    curahHujan(20, '2026-11-01 07:00:00', 2.22);
    curahHujan(20, '2026-11-01 12:00:00', 4.44);
    curahHujan(21, '2026-11-01 07:00:00', 3.33);

    $this->actingAs(akun('superadmin'))->get('/admin/curah-hujan?pos=20&bulan=2026-11&jam=7')
        ->assertOk()
        ->assertSee('2,22')
        ->assertDontSee('1,11')
        ->assertDontSee('3,33')
        ->assertDontSee('4,44');
});

it('menambah data; pos non-ARR, nilai negatif, dan duplikat jam ditolak', function () {
    $this->actingAs($admin = akun('superadmin'));
    $isi = ['pos_id' => 20, 'tanggal' => '2026-11-01', 'jam' => 7, 'hujan' => '15.5'];

    $this->post('/admin/curah-hujan', $isi)->assertRedirect('/admin/curah-hujan');
    $data = PengamatanArr::sole();
    expect($data->waktu->toDateTimeString())->toBe('2026-11-01 07:00:00')
        ->and($data->hujan)->toBe(15.5)
        ->and($data->diinput_oleh)->toBe($admin->id);

    $this->post('/admin/curah-hujan', $isi)->assertSessionHasErrors('jam');
    $this->post('/admin/curah-hujan', ['pos_id' => 10] + $isi)->assertSessionHasErrors('pos_id');
    $this->post('/admin/curah-hujan', ['hujan' => '-1', 'jam' => 12] + $isi)->assertSessionHasErrors('hujan');
});

it('petugas mencatat untuk posnya sendiri pada hari ini', function () {
    $this->actingAs(akun('petugas_pos', ['pos_id' => 20]));

    $this->post('/admin/curah-hujan', ['pos_id' => 21, 'tanggal' => '2020-01-01', 'jam' => 7, 'hujan' => '0'])->assertRedirect();

    $data = PengamatanArr::sole();
    expect($data->pos_id)->toBe(20)
        ->and($data->waktu->toDateTimeString())->toBe(today()->setHour(7)->toDateTimeString());
});

it('tidak bisa mengubah atau menghapus data pos lain', function () {
    $lain = curahHujan(21, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/curah-hujan/{$lain->id}/ubah")->assertNotFound();
    $this->put("/admin/curah-hujan/{$lain->id}", ['pos_id' => 20, 'tanggal' => '2026-11-01', 'jam' => 7])->assertNotFound();
    $this->delete("/admin/curah-hujan/{$lain->id}")->assertNotFound();
    expect($lain->fresh())->not->toBeNull();
});

it('mengubah, mencabut validasi, dan menghapus data', function () {
    $data = curahHujan(20, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/curah-hujan/{$data->id}/ubah")->assertOk()
        ->assertSeeInOrder(['Curah Hujan', 'Ubah data', 'ARR Sermo', '2026-11-01', 'Tervalidasi', 'Hapus', 'Simpan perubahan'])
        ->assertSee('data-konfirmasi="Data curah hujan ARR Sermo', false);
    $this->put("/admin/curah-hujan/{$data->id}", ['pos_id' => 20, 'tanggal' => '2026-11-01', 'jam' => 7, 'hujan' => '30.25'])->assertRedirect();
    expect($data->fresh())->hujan->toBe(30.25)->tervalidasi->toBeFalse();

    $this->delete("/admin/curah-hujan/{$data->id}")->assertRedirect();
    expect($data->fresh())->toBeNull();
});

it('form tambah dan menu sidebar tersedia', function () {
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/curah-hujan/tambah')->assertOk()->assertSee('Tambah Data Curah Hujan');
    $this->get('/admin')->assertSee(route('admin.curah-hujan'));
});

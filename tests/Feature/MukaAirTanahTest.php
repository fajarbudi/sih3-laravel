<?php

use App\Models\PengamatanMat;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 40, 'walidata_id' => 1, 'nama' => 'Sumur Pantau Wates', 'tipe' => 'hidrogeologi', 'jenis' => 'mat', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 41, 'walidata_id' => 11, 'nama' => 'Sumur PJT', 'tipe' => 'hidrogeologi', 'jenis' => 'mat', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
});

function mukaAirTanah(int $posId, string $waktu, float $nilai = -6.5): PengamatanMat
{
    return PengamatanMat::create(['pos_id' => $posId, 'waktu' => $waktu, 'muka_air_tanah' => $nilai]);
}

it('admin walidata hanya melihat data muka air tanah walidatanya', function () {
    mukaAirTanah(40, '2026-11-01 07:00:00', -6.25);
    mukaAirTanah(41, '2026-11-01 07:00:00', -9.75);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/muka-air-tanah')
        ->assertOk()
        ->assertSee('Kedudukan MAT (M)')
        ->assertSee('-6,25')
        ->assertDontSee('-9,75');
});

it('menyaring menurut pos, jam, dan bulan', function () {
    mukaAirTanah(40, '2026-10-31 17:00:00', -1.11);
    mukaAirTanah(40, '2026-11-01 07:00:00', -2.22);
    mukaAirTanah(40, '2026-11-01 12:00:00', -4.44);
    mukaAirTanah(41, '2026-11-01 07:00:00', -3.33);

    $this->actingAs(akun('superadmin'))->get('/admin/muka-air-tanah?pos=40&bulan=2026-11&jam=7')
        ->assertOk()
        ->assertSee('-2,22')
        ->assertDontSee('-1,11')
        ->assertDontSee('-3,33')
        ->assertDontSee('-4,44');
});

it('menambah data negatif; pos non-MAT dan duplikat jam ditolak', function () {
    $this->actingAs($admin = akun('superadmin'));
    $isi = ['pos_id' => 40, 'tanggal' => '2026-11-01', 'jam' => 7, 'muka_air_tanah' => '-7.35'];

    $this->post('/admin/muka-air-tanah', $isi)->assertRedirect('/admin/muka-air-tanah');
    $data = PengamatanMat::sole();
    expect($data->waktu->toDateTimeString())->toBe('2026-11-01 07:00:00')
        ->and($data->muka_air_tanah)->toBe(-7.35)
        ->and($data->diinput_oleh)->toBe($admin->id);

    $this->post('/admin/muka-air-tanah', $isi)->assertSessionHasErrors('jam');
    $this->post('/admin/muka-air-tanah', ['pos_id' => 20] + $isi)->assertSessionHasErrors('pos_id');
    $this->post('/admin/muka-air-tanah', ['muka_air_tanah' => 'dalam', 'jam' => 12] + $isi)->assertSessionHasErrors('muka_air_tanah');
});

it('petugas mencatat untuk posnya sendiri pada hari ini', function () {
    $this->actingAs(akun('petugas_pos', ['pos_id' => 40]));

    $this->post('/admin/muka-air-tanah', ['pos_id' => 41, 'tanggal' => '2020-01-01', 'jam' => 7, 'muka_air_tanah' => '-5'])->assertRedirect();

    $data = PengamatanMat::sole();
    expect($data->pos_id)->toBe(40)
        ->and($data->waktu->toDateTimeString())->toBe(today()->setHour(7)->toDateTimeString());
});

it('tidak bisa mengubah atau menghapus data pos lain', function () {
    $lain = mukaAirTanah(41, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/muka-air-tanah/{$lain->id}/ubah")->assertNotFound();
    $this->put("/admin/muka-air-tanah/{$lain->id}", ['pos_id' => 40, 'tanggal' => '2026-11-01', 'jam' => 7])->assertNotFound();
    $this->delete("/admin/muka-air-tanah/{$lain->id}")->assertNotFound();
    expect($lain->fresh())->not->toBeNull();
});

it('mengubah, mencabut validasi, dan menghapus data', function () {
    $data = mukaAirTanah(40, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/muka-air-tanah/{$data->id}/ubah")->assertOk()
        ->assertSeeInOrder(['Muka Air Tanah', 'Ubah data', 'Sumur Pantau Wates', '2026-11-01', 'Kedudukan MAT', 'Tervalidasi', 'Hapus', 'Simpan perubahan'])
        ->assertSee('data-konfirmasi="Data muka air tanah Sumur Pantau Wates', false);
    $this->put("/admin/muka-air-tanah/{$data->id}", ['pos_id' => 40, 'tanggal' => '2026-11-01', 'jam' => 7, 'muka_air_tanah' => '-8.1'])->assertRedirect();
    expect($data->fresh())->muka_air_tanah->toBe(-8.1)->tervalidasi->toBeFalse();

    $this->delete("/admin/muka-air-tanah/{$data->id}")->assertRedirect();
    expect($data->fresh())->toBeNull();
});

it('form tambah dan menu sidebar tersedia', function () {
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/muka-air-tanah/tambah')->assertOk()->assertSee('Tambah Data Muka Air Tanah');
    $this->get('/admin')->assertSee(route('admin.muka-air-tanah'));
});

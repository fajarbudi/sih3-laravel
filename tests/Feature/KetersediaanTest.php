<?php

use App\Models\PengamatanArr;
use App\Models\PengamatanAwlr;
use App\Models\PengamatanKualitasAir;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 12, 'walidata_id' => 1, 'nama' => 'Bendung Ancol', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 13, 'walidata_id' => 1, 'nama' => 'Bendung Kosong', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 11, 'walidata_id' => 11, 'nama' => 'Bendung PJT', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 50, 'walidata_id' => 1, 'nama' => 'KA Progo', 'tipe' => 'hidrologi', 'jenis' => 'kualitas_air', 'metode' => 'manual']);
});

it('menghitung jumlah data per pos per bulan, hanya pos yang punya data, urut nama', function () {
    foreach ([7, 12, 17] as $jam) {
        PengamatanAwlr::create(['pos_id' => 10, 'waktu' => "2025-01-05 {$jam}:00:00"]);
    }
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => '2025-03-01 07:00:00']);
    PengamatanAwlr::create(['pos_id' => 12, 'waktu' => '2025-12-31 17:00:00']);
    PengamatanAwlr::create(['pos_id' => 12, 'waktu' => '2024-12-31 17:00:00']); // tahun lain

    $this->actingAs(akun('superadmin'))->get('/admin/ketersediaan/duga-air?tahun=2025')
        ->assertOk()
        ->assertSee('Ketersediaan Duga Air (AWLR)')
        ->assertSeeInOrder(['No.', 'POS', 'Jan', 'Feb', 'Mar', 'Des'])
        ->assertSeeInOrder(['Bendung Ancol', '1', 'Bendung Glapan', '3', '1'])
        ->assertSee('2 pos');
});

it('default tahun berjalan dan dibatasi pada walidata pengguna', function () {
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => now()->startOfYear()->setHour(7)]);
    PengamatanAwlr::create(['pos_id' => 11, 'waktu' => now()->startOfYear()->setHour(7)]);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/ketersediaan/duga-air')
        ->assertOk()
        ->assertSee('Tahun '.now()->year)
        ->assertSee('1 pos')
        ->assertDontSee('Bendung PJT');
});

it('jenis lain memakai tabel pengamatannya sendiri; menu menjadi tautan', function () {
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2025-02-01 07:00:00', 'hujan' => 1]);
    PengamatanKualitasAir::create(['pos_id' => 50, 'waktu' => '2025-06-01']);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/ketersediaan/curah-hujan?tahun=2025')->assertOk()->assertSee('Curah Hujan (ARR)')->assertSee('ARR Sermo')->assertDontSee('Bendung');
    $this->get('/admin/ketersediaan/kualitas-air?tahun=2025')->assertOk()->assertSee('KA Progo');
    $this->get('/admin/ketersediaan/muka-air-tanah?tahun=2025')->assertOk()->assertSee('Tidak ada data pada tahun 2025');
    $this->get('/admin')
        ->assertSee(route('admin.ketersediaan.duga-air'))
        ->assertSee(route('admin.ketersediaan.muka-air-tanah'));
});

<?php

use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use App\Models\TelemetriArr;
use App\Models\TelemetriAwlr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 60, 'walidata_id' => 11, 'nama' => 'AWLR WADAS LINTANG', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'telemetri']);
    Pos::forceCreate(['id' => 61, 'walidata_id' => 11, 'nama' => 'AWLR SEMPOR', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'telemetri']);
    Pos::forceCreate(['id' => 62, 'walidata_id' => 1, 'nama' => 'ATAB Prambanan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'telemetri']);
    Pos::forceCreate(['id' => 70, 'walidata_id' => 11, 'nama' => 'ARR WADAS LINTANG', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'telemetri']);
    Pos::forceCreate(['id' => 71, 'walidata_id' => 1, 'nama' => 'ARR Bantar DIY', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'telemetri']);
    PerangkatTelemetri::create(['pos_id' => 60, 'sumber' => 'pjt', 'kode_perangkat' => 'AWLR WADAS LINTANG', 'faktor_skala' => 1]);
});

it('menampilkan telemetri PJT dalam meter, terbaru di atas, hanya pos PJT', function () {
    TelemetriAwlr::create(['pos_id' => 60, 'sumber' => 'pjt', 'waktu' => '2026-11-19 03:00:00', 'tma' => 167.41]);
    TelemetriAwlr::create(['pos_id' => 61, 'sumber' => 'pjt', 'waktu' => '2026-11-19 04:00:00', 'tma' => 58.93]);
    TelemetriAwlr::create(['pos_id' => 62, 'sumber' => 'pjt', 'waktu' => '2026-11-19 05:00:00', 'tma' => 23.55]); // salinan pos BBWS di tabel lama
    TelemetriAwlr::create(['pos_id' => 60, 'sumber' => 'tatonas', 'waktu' => '2026-11-19 06:00:00', 'tma' => 11.11]);

    $this->actingAs(akun('superadmin'))->get('/admin/duga-air-pjt')
        ->assertOk()
        ->assertSeeInOrder(['No.', 'POS', 'Waktu', 'Tinggi Muka Air (m)'])
        ->assertSeeInOrder(['AWLR SEMPOR', '58,93', 'AWLR WADAS LINTANG', '167,41'])
        ->assertSee('2 data')
        ->assertDontSee('23,55')
        ->assertDontSee('11,11')
        ->assertDontSee('/ubah');
});

it('menyaring menurut pos dan bulan', function () {
    TelemetriAwlr::create(['pos_id' => 60, 'sumber' => 'pjt', 'waktu' => '2026-10-31 23:00:00', 'tma' => 1.11]);
    TelemetriAwlr::create(['pos_id' => 60, 'sumber' => 'pjt', 'waktu' => '2026-11-01 00:00:00', 'tma' => 2.22]);
    TelemetriAwlr::create(['pos_id' => 61, 'sumber' => 'pjt', 'waktu' => '2026-11-01 00:00:00', 'tma' => 3.33]);

    $this->actingAs(akun('superadmin'))->get('/admin/duga-air-pjt?pos=60&bulan=2026-11')
        ->assertOk()->assertSee('2,22')->assertDontSee('1,11')->assertDontSee('3,33');
});

it('admin walidata lain tidak melihat data PJT; menu menjadi tautan', function () {
    TelemetriAwlr::create(['pos_id' => 60, 'sumber' => 'pjt', 'waktu' => '2026-11-19 03:00:00', 'tma' => 167.41]);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/duga-air-pjt')
        ->assertOk()->assertDontSee('167,41')->assertSee('Belum ada data telemetri PJT');
    $this->actingAs(akun('superadmin'))->get('/admin')->assertSee(route('admin.duga-air-pjt'));
});

it('curah hujan PJT: kolom seperti aplikasi lama, hanya pos ARR PJT', function () {
    TelemetriArr::create(['pos_id' => 70, 'sumber' => 'pjt', 'waktu' => '2026-11-19 03:00:00', 'hujan' => 12.5]);
    TelemetriArr::create(['pos_id' => 71, 'sumber' => 'pjt', 'waktu' => '2026-11-19 03:00:00', 'hujan' => 77.7]); // pos walidata lain di tabel lama
    TelemetriAwlr::create(['pos_id' => 60, 'sumber' => 'pjt', 'waktu' => '2026-11-19 03:00:00', 'tma' => 167.41]);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/curah-hujan-pjt')
        ->assertOk()
        ->assertSee('Curah Hujan PJT')
        ->assertSeeInOrder(['POS', 'Waktu', 'Curah Hujan (mm)', 'ARR WADAS LINTANG', '12,5'])
        ->assertDontSee('77,7')
        ->assertDontSee('167,41');
    $this->get('/admin')->assertSee(route('admin.curah-hujan-pjt'));
});

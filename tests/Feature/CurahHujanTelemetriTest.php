<?php

use App\Models\Pos;
use App\Models\RekapArrPerJam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'telemetri']);
    Pos::forceCreate(['id' => 21, 'walidata_id' => 11, 'nama' => 'ARR Wadaslintang', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'telemetri']);
    Pos::forceCreate(['id' => 22, 'walidata_id' => 1, 'nama' => 'ARR Tanpa Data', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'telemetri']);
});

function rekapHujan(int $posId, string $jam, float $hujan): void
{
    RekapArrPerJam::query()->insert(['pos_id' => $posId, 'jam' => $jam, 'hujan' => $hujan, 'jumlah_data' => 12]);
}

it('menampilkan hari hujan 07.00 kemarin s.d. 06.59 hari terpilih, per tanggal dan jam, dengan total', function () {
    rekapHujan(20, '2026-11-09 07:00:00', 1.5);   // jam 07 hari sebelumnya: kolom pertama
    rekapHujan(20, '2026-11-10 06:00:00', 2.25);  // jam 06 tanggal terpilih: kolom terakhir
    rekapHujan(20, '2026-11-10 07:00:00', 99);    // sudah hari hujan berikutnya
    rekapHujan(20, '2026-11-09 06:00:00', 88);    // masih hari hujan sebelumnya
    rekapHujan(21, '2026-11-09 12:00:00', 0);

    $this->actingAs(akun('superadmin'))->get('/admin/curah-hujan-telemetri?tanggal=2026-11-10')
        ->assertOk()
        ->assertSeeInOrder(['Senin, 9 November 2026', 'Selasa, 10 November 2026', 'Total', '07:00', '23:00', '00:00', '06:00'])
        ->assertSeeInOrder(['ARR Sermo', 'BBWS SERAYU OPAK', '1,50', '2,25', '3,75', 'ARR Wadaslintang', '0,00'])
        ->assertSee('2 pos')
        ->assertDontSee('99,00')
        ->assertDontSee('88,00');
});

it('default hari ini dan dibatasi pada walidata pengguna', function () {
    rekapHujan(21, today()->setHour(3)->toDateTimeString(), 4.4);
    rekapHujan(20, today()->setHour(3)->toDateTimeString(), 5.5);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/curah-hujan-telemetri')
        ->assertOk()
        ->assertSee('value="'.today()->toDateString().'"', false)
        ->assertSee('5,50')
        ->assertDontSee('4,40');
    $this->actingAs(akun('superadmin'))->get('/admin')->assertSee(route('admin.curah-hujan-telemetri'));
});

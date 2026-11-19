<?php

use App\Models\PengamatanArr;
use App\Models\PengamatanAwlr;
use App\Models\PengamatanKlimatologi;
use App\Models\PengamatanMat;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 11, 'walidata_id' => 11, 'nama' => 'Bendung PJT', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 30, 'walidata_id' => 1, 'nama' => 'Klimat Bantul', 'tipe' => 'hidrometeorologi', 'jenis' => 'klimatologi', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 21, 'walidata_id' => 1, 'nama' => 'ARR Godean', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 40, 'walidata_id' => 1, 'nama' => 'Sumur Wates', 'tipe' => 'hidrogeologi', 'jenis' => 'mat', 'metode' => 'manual']);
});

it('default bulan berjalan, kolom seperti aplikasi lama, terakhir diinput di atas, tanpa aksi ubah', function () {
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => now()->startOfMonth()->setHour(17), 'limpas' => 1.111, 'debit_suplesi' => 2.5]);
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => now()->startOfMonth()->setHour(7), 'limpas' => 2.222]);
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => now()->subMonthNoOverflow()->startOfMonth()->setHour(7), 'limpas' => 9.999]);

    $this->actingAs(akun('superadmin'))->get('/admin/rekap/duga-air')
        ->assertOk()
        ->assertSee('value="'.now()->format('Y-m').'"', false)
        ->assertSeeInOrder(['No.', 'POS', 'Waktu', 'Muka Air Limpas (m)', 'Muka Air Intake Kiri (m)', 'Muka Air Intake Kanan (m)', 'Muka Air PDAM (m)', 'Muka Air Pembilas Kiri (m)', 'Muka Air Pembilas Kanan (m)', 'Muka Air Suplesi (m)'])
        ->assertSeeInOrder(['2,222', '1,111'])
        ->assertSee('2,5')
        ->assertDontSee('9,999')
        ->assertDontSee('/ubah');
});

it('menampilkan semua baris sebulan tanpa paginasi', function () {
    foreach (range(1, 28) as $hari) {
        foreach ([7, 12] as $jam) {
            PengamatanAwlr::create(['pos_id' => 10, 'waktu' => "2026-02-{$hari} {$jam}:00:00", 'limpas' => 1]);
        }
    }

    $this->actingAs(akun('superadmin'))->get('/admin/rekap/duga-air?bulan=2026-02')
        ->assertOk()
        ->assertSee('56 data')
        ->assertSee('56.')
        ->assertDontSee('rel="next"', false);
});

it('dibatasi pada pos walidata pengguna (aplikasi lama tidak membatasi)', function () {
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => '2026-11-01 07:00:00', 'limpas' => 1.234]);
    PengamatanAwlr::create(['pos_id' => 11, 'waktu' => '2026-11-01 07:00:00', 'limpas' => 5.678]);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/rekap/duga-air?bulan=2026-11&walidata=11')
        ->assertOk()->assertSee('1,234')->assertDontSee('5,678');
});

it('menyediakan cetak dan unduhan Excel sesuai filter', function () {
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => '2026-11-01 07:00:00', 'limpas' => 1.234]);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/rekap/duga-air?bulan=2026-11')
        ->assertSee(route('admin.rekap.duga-air', ['bulan' => '2026-11', 'format' => 'cetak']))
        ->assertSee(route('admin.rekap.duga-air', ['bulan' => '2026-11', 'format' => 'excel']));

    $this->get('/admin/rekap/duga-air?bulan=2026-11&format=cetak')
        ->assertOk()->assertSee('Rekapitulasi Duga Air November 2026')->assertSee('1,234')->assertSee('window.print()', false);

    $this->get('/admin/rekap/duga-air?bulan=2026-11&format=excel')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="rekap-duga-air-2026-11.xls"')
        ->assertSee('1.234')
        ->assertDontSee('window.print()', false);
});

it('menu Rekap Duga Air di sidebar menjadi tautan', function () {
    $this->actingAs(akun('superadmin'))->get('/admin')->assertSee(route('admin.rekap.duga-air'));
});

it('rekap klimatologi: kolom seperti aplikasi lama, hanya pos klimatologi, cetak dan Excel', function () {
    PengamatanKlimatologi::create(['pos_id' => 30, 'waktu' => '2026-11-02 07:00:00', 'kelembapan' => 81.5, 'suhu_air_min' => 11.11, 'hujan' => 12.25]);
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => '2026-11-02 07:00:00', 'limpas' => 7.777]);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/rekap/klimatologi?bulan=2026-11')
        ->assertOk()
        ->assertSee('Rekap Klimatologi')
        ->assertSeeInOrder(['POS', 'Waktu', 'RH', 'Temperatur Maksimal', 'Temperatur Minimal', 'Temperatur Rata-rata', 'Temperatur Air', 'Penguapan', 'Kecepatan Angin', 'Radiasi Matahari', 'Lama Penyinaran', 'Hujan'])
        ->assertSee('Klimat Bantul')
        ->assertSee('81,5')
        ->assertSee('12,25')
        ->assertDontSee('11,11') // temperatur air min/maks tidak ada di rekap lama
        ->assertDontSee('7,777');

    $this->get('/admin/rekap/klimatologi?bulan=2026-11&format=cetak')->assertOk()->assertSee('Rekapitulasi Klimatologi November 2026');
    $this->get('/admin/rekap/klimatologi?bulan=2026-11&format=excel')
        ->assertHeader('Content-Disposition', 'attachment; filename="rekap-klimatologi-2026-11.xls"')
        ->assertSee('81.5');
    $this->get('/admin')->assertSee(route('admin.rekap.klimatologi'));
});

it('rekap curah hujan: baris per pos dan tanggal, kolom Jan-Des, sel rata-rata harian 2 desimal', function () {
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2026-01-05 07:00:00', 'hujan' => 10]);
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2026-01-05 12:00:00', 'hujan' => 5]);
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2026-01-05 17:00:00', 'hujan' => null]); // kosong tidak dihitung 0
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2026-03-05 07:00:00', 'hujan' => 3.456]);
    PengamatanArr::create(['pos_id' => 21, 'waktu' => '2026-02-01 07:00:00', 'hujan' => 1]);
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2025-01-05 07:00:00', 'hujan' => 99]);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/rekap/curah-hujan?tahun=2026')
        ->assertOk()
        ->assertSeeInOrder(['No.', 'POS', 'Tanggal', 'Jan (mm)', 'Feb (mm)', 'Des (mm)'])
        // diurutkan nama pos: ARR Godean dulu, lalu ARR Sermo tanggal 5 (Jan 7,50 dan Mar 3,46 dalam satu baris)
        ->assertSeeInOrder(['ARR Godean', '1,00', 'ARR Sermo', '5', '7,50', '3,46'])
        ->assertSee('2 baris')
        ->assertDontSee('99,00');

    $this->get('/admin/rekap/curah-hujan?tahun=2026&format=excel')
        ->assertHeader('Content-Disposition', 'attachment; filename="rekap-curah-hujan-2026.xls"')
        ->assertSee('7.50');
});

it('rekap muka air tanah: satu baris per pos, default tahun berjalan', function () {
    PengamatanMat::create(['pos_id' => 40, 'waktu' => now()->startOfYear()->addDays(14)->setHour(7), 'muka_air_tanah' => -10.065]);
    PengamatanMat::create(['pos_id' => 40, 'waktu' => now()->subYear()->startOfYear()->setHour(7), 'muka_air_tanah' => -5.5]);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/rekap/muka-air-tanah')
        ->assertOk()
        ->assertSee('Rekap Muka Air Tanah')
        ->assertSee('Tahun '.now()->year)
        ->assertSeeInOrder(['POS', 'Jan (M)', 'Des (M)', 'Sumur Wates', '-10,07'])
        ->assertDontSee('>Tanggal<', false)
        ->assertDontSee('-5,50');

    $this->get('/admin/rekap/muka-air-tanah?format=cetak')->assertOk()->assertSee('Rekapitulasi Muka Air Tanah Tahun '.now()->year);
    $this->get('/admin')->assertSee(route('admin.rekap.curah-hujan'))->assertSee(route('admin.rekap.muka-air-tanah'));
});

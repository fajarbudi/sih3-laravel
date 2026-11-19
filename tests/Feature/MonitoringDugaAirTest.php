<?php

use App\Models\PengamatanAwlr;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 12, 'walidata_id' => 1, 'nama' => 'Bendung Ancol', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 11, 'walidata_id' => 11, 'nama' => 'Bendung PJT', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

it('menampilkan pembacaan kemarin per jam, x bila kosong, dan menandai pos yang mengisi', function () {
    $kemarin = today()->subDay();
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => $kemarin->copy()->setHour(7), 'limpas' => 1.5, 'intake_kanan' => 2.5, 'intake_kiri' => null]);
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => today()->setHour(7), 'limpas' => 9.999]); // hari ini, bukan kemarin

    $this->actingAs(akun('superadmin'))->get('/admin/monitoring-duga-air')
        ->assertOk()
        ->assertSee('value="'.$kemarin->toDateString().'"', false)
        ->assertSee('1 dari 3 pos mengisi')
        ->assertSeeInOrder(['Jam 07.00 (m)', 'Jam 12.00 (m)', 'Jam 17.00 (m)', 'Limpas', 'Intake Kanan', 'Intake Kiri', 'Rata'])
        // diurutkan nama: Ancol (belum), Glapan (sudah, hijau), PJT (belum)
        ->assertSeeInOrder(['Bendung Ancol', 'belum mengisi', 'Bendung Glapan', 'sudah mengisi', '1,5', '2,5', 'x', '2,00', 'Bendung PJT'])
        ->assertSee('bg-green-50', false)
        ->assertDontSee('9,999');
});

it('tanggal bisa dipilih dan data dibatasi pada walidata pengguna', function () {
    PengamatanAwlr::create(['pos_id' => 11, 'waktu' => '2026-11-01 12:00:00', 'limpas' => 3.3]);
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => '2026-11-01 12:00:00', 'limpas' => 4.4]);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/monitoring-duga-air?tanggal=2026-11-01')
        ->assertOk()
        ->assertSee('4,4')
        ->assertSee('1 dari 2 pos mengisi')
        ->assertDontSee('Bendung PJT')
        ->assertDontSee('Semua walidata');
});

it('menu Monitoring Duga Air di sidebar menjadi tautan', function () {
    $this->actingAs(akun('superadmin'))->get('/admin')->assertSee(route('admin.monitoring-duga-air'));
});

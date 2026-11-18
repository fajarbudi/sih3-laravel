<?php

use App\Models\Buletin;
use App\Models\Pengguna;
use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Pos BBWS', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'telemetri']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 11, 'nama' => 'Pos PJT', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
});

function pengguna(string $peran, array $atribut = []): Pengguna
{
    return Pengguna::create($atribut + ['username' => $peran, 'nama' => ucfirst($peran), 'peran' => $peran, 'password' => 'rahasia123', 'aktif' => true]);
}

it('mengarahkan tamu ke halaman login', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->get('/login')->assertOk()->assertSee('images/logo-header-admin.png')->assertSee('BBWS Serayu Opak');
});

it('login dengan password bcrypt', function () {
    pengguna('superadmin');

    $this->post('/login', ['username' => 'superadmin', 'password' => 'rahasia123'])->assertRedirect('/admin');

    $this->assertAuthenticated();
    expect(Pengguna::first()->terakhir_login_at)->not->toBeNull();
});

it('login akun lama ber-MD5 lalu mengubahnya ke bcrypt', function () {
    $lama = Pengguna::create(['username' => 'petugas', 'nama' => 'Petugas', 'peran' => 'petugas_pos', 'password_md5' => md5('lama123'), 'aktif' => true]);

    $this->post('/login', ['username' => 'petugas', 'password' => 'lama123'])->assertRedirect('/admin');

    $lama->refresh();
    expect($lama->password_md5)->toBeNull()
        ->and(Hash::check('lama123', $lama->password))->toBeTrue();
});

it('menolak password salah dan akun tidak aktif', function () {
    pengguna('superadmin');
    pengguna('admin_walidata', ['aktif' => false]);

    $this->post('/login', ['username' => 'superadmin', 'password' => 'salah'])->assertSessionHasErrors(['username' => 'Username atau password salah.']);
    $this->post('/login', ['username' => 'admin_walidata', 'password' => 'rahasia123'])->assertSessionHasErrors(['username' => 'Akun tidak aktif.']);
    $this->assertGuest();
});

it('logout', function () {
    $this->actingAs(pengguna('superadmin'))->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('dashboard superadmin: ringkasan, data grafik, dan buletin draf', function () {
    PerangkatTelemetri::create(['pos_id' => 10, 'sumber' => 'pjt', 'kode_perangkat' => 'A', 'terakhir_diterima_at' => now('Asia/Jakarta')->toDateTimeString()]);
    PerangkatTelemetri::create(['pos_id' => 10, 'sumber' => 'tatonas', 'kode_perangkat' => '4104', 'terakhir_diterima_at' => '2026-01-01 00:00:00']);
    Buletin::create(['tipe' => 'otomatis', 'judul' => 'Draf BMKG', 'terbit' => false, 'tanggal' => now()]);
    $jamIni = now('Asia/Jakarta')->startOfHour()->toDateTimeString();
    DB::table('telemetri_arr')->insert(['pos_id' => 20, 'sumber' => 'pjt', 'waktu' => $jamIni, 'hujan' => 12.5]);
    DB::table('rekap_arr_per_jam')->insert(['pos_id' => 20, 'jam' => $jamIni, 'hujan' => 12.5, 'jumlah_data' => 1]);

    $this->actingAs(pengguna('superadmin'))->get('/admin')
        ->assertOk()
        ->assertViewHas('posPerJenis', fn ($p) => $p->sum() === 2)
        ->assertViewHas('jumlahPerangkat', 2)
        ->assertViewHas('perangkatAktif', 1)
        ->assertViewHas('buletinDraf', 1)
        ->assertViewHas('statusSumber', fn ($s) => $s['Perum Jasa Tirta I'] === ['aktif' => 1, 'jumlah' => 1] && $s['Tatonas'] === ['aktif' => 0, 'jumlah' => 1])
        ->assertViewHas('hujanPerPos', fn ($h) => $h->all() === ['Pos PJT' => 12.5])
        ->assertViewHas('hujanPerJam', fn ($h) => count($h) === 48 && end($h)['hujan'] === 12.5 && end($h)['pos'] === 'Pos PJT')
        ->assertViewHas('bacaanPerHari', fn ($d) => count($d['tanggal']) === 14 && array_sum($d['seri']['Curah hujan']) === 1)
        ->assertSee('Kapan hujan turun dalam 48 jam terakhir?')
        ->assertSee('Lihat data dalam tabel')
        ->assertSee('chart.umd.min.js', false)
        ->assertSee("new Chart(el('grafik-hujan-jam')", false)
        ->assertSee('Tidak ada data baru');
});

it('dashboard menampilkan keadaan kosong bila belum ada data telemetri', function () {
    $this->actingAs(pengguna('superadmin'))->get('/admin')
        ->assertOk()
        ->assertSee('Tidak ada hujan tercatat dalam 48 jam terakhir.')
        ->assertSee('Belum ada bacaan telemetri dalam 14 hari terakhir.')
        ->assertSee('Belum ada perangkat telemetri yang terdaftar.');
});

it('dashboard admin walidata hanya menghitung pos walidatanya, tanpa buletin draf', function () {
    $this->actingAs(pengguna('admin_walidata', ['walidata_id' => 11]))->get('/admin')
        ->assertOk()
        ->assertViewHas('posPerJenis', fn ($p) => $p->sum() === 1)
        ->assertViewHas('posPerJenis', fn ($p) => $p->keys()->all() === ['ARR'])
        ->assertViewHas('buletinDraf', null)
        ->assertDontSee('Buletin BMKG belum terbit');
});

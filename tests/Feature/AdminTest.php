<?php

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

it('dashboard superadmin: hujan dengan kategori BMKG, muka air dalam meter, dan cuaca', function () {
    $z = 'Asia/Jakarta';
    $kini = now($z)->startOfHour();
    // Pos 10 (AWLR, PJT): TMA dikirim dalam cm, faktor_skala 0.01 -> 1,30 m kemarin, 1,40 m sekarang.
    PerangkatTelemetri::create(['pos_id' => 10, 'sumber' => 'pjt', 'kode_perangkat' => 'A', 'faktor_skala' => 0.01, 'terakhir_diterima_at' => $kini->toDateTimeString()]);
    DB::table('telemetri_awlr')->insert([
        ['pos_id' => 10, 'sumber' => 'pjt', 'waktu' => $kini->copy()->subDay()->toDateTimeString(), 'tma' => 130],
        ['pos_id' => 10, 'sumber' => 'pjt', 'waktu' => $kini->toDateTimeString(), 'tma' => 140],
    ]);
    DB::table('rekap_awlr_per_jam')->insert([
        ['pos_id' => 10, 'jam' => $kini->copy()->subHours(2)->toDateTimeString(), 'tma_rata' => 135, 'jumlah_data' => 1],
        ['pos_id' => 10, 'jam' => $kini->toDateTimeString(), 'tma_rata' => 140, 'jumlah_data' => 1],
    ]);
    DB::table('telemetri_arr')->insert(['pos_id' => 20, 'sumber' => 'pjt', 'waktu' => $kini->toDateTimeString(), 'hujan' => 12.5]);
    DB::table('rekap_arr_per_jam')->insert(['pos_id' => 20, 'jam' => $kini->toDateTimeString(), 'hujan' => 12.5, 'jumlah_data' => 1]);
    Pos::forceCreate(['id' => 30, 'walidata_id' => 1, 'nama' => 'Klimatologi Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'klimatologi', 'metode' => 'telemetri']);
    DB::table('telemetri_klimatologi')->insert(['pos_id' => 30, 'sumber' => 'sermo', 'waktu' => $kini->toDateTimeString(), 'suhu' => 25.3, 'kelembapan' => 93]);

    $this->actingAs(pengguna('superadmin'))->get('/admin')
        ->assertOk()
        ->assertViewHas('hujanPerPos', fn ($h) => $h->all() === ['Pos PJT' => 12.5])
        ->assertViewHas('hujanPerJam', fn ($h) => count($h) === 48 && end($h)['hujan'] === 12.5 && end($h)['pos'] === 'Pos PJT')
        ->assertViewHas('mukaAir', fn ($m) => $m->count() === 1 && $m[0]['tma'] === 1.4 && $m[0]['perubahan'] === 0.1 && $m[0]['jejak'] === [1.35, 1.4])
        ->assertViewHas('posNaik', 1)
        ->assertViewHas('cuaca', fn ($c) => $c->count() === 1 && $c[0]->nama === 'Klimatologi Sermo')
        ->assertSee('Hujan ringan')
        ->assertSee('↑ 10 cm')
        ->assertSee('Muka air')
        ->assertSee("new Chart(document.getElementById('grafik-hujan-jam')", false);
});

it('kategori hujan harian mengikuti BMKG', function () {
    expect(array_map([App\Services\KondisiTerkini::class, 'kategoriHujan'], [0.4, 5, 19.9, 20, 57.8, 100, 151]))
        ->toBe(['Sangat ringan', 'Ringan', 'Ringan', 'Sedang', 'Lebat', 'Sangat lebat', 'Ekstrem']);
});

it('dashboard menampilkan keadaan kosong bila belum ada data telemetri', function () {
    $this->actingAs(pengguna('superadmin'))->get('/admin')
        ->assertOk()
        ->assertSee('Tidak ada hujan tercatat dalam 24 jam terakhir.')
        ->assertSee('Belum ada data muka air telemetri dalam 7 hari terakhir.')
        ->assertSee('Belum ada data klimatologi telemetri dalam 7 hari terakhir.');
});

it('dashboard admin walidata hanya melihat data pos walidatanya', function () {
    DB::table('rekap_arr_per_jam')->insert([
        ['pos_id' => 10, 'jam' => now('Asia/Jakarta')->startOfHour()->toDateTimeString(), 'hujan' => 30, 'jumlah_data' => 1],
        ['pos_id' => 20, 'jam' => now('Asia/Jakarta')->startOfHour()->toDateTimeString(), 'hujan' => 4, 'jumlah_data' => 1],
    ]);

    $this->actingAs(pengguna('admin_walidata', ['walidata_id' => 11]))->get('/admin')
        ->assertOk()
        ->assertViewHas('hujanPerPos', fn ($h) => $h->all() === ['Pos PJT' => 4.0]);
});

it('menu sidebar superadmin berisi semua grup dan semua modul sudah dibuat (tidak ada lagi "Segera")', function () {
    $this->actingAs(pengguna('superadmin'))->get('/admin')
        ->assertOk()
        ->assertSeeInOrder(['Dashboard', 'Halaman Depan', 'H3', 'Hidrologi', 'Hidrogeologi', 'Hidrometeorologi', 'Ketersediaan data', 'Unduh Data', 'Ketersediaan Data', 'Pengaturan', 'Publikasi', 'Catatan Kejadian', 'Ambang Batas', 'Input'])
        ->assertSee('href="'.route('admin.dashboard').'"', false)
        ->assertDontSee('aria-disabled="true"', false)
        ->assertDontSee('Segera');
});

it('menu sidebar admin walidata tanpa Publikasi, Input, dan menu pengaturan sistem', function () {
    $this->actingAs(pengguna('admin_walidata', ['walidata_id' => 11]))->get('/admin')
        ->assertOk()
        ->assertSee('Unduh Data')
        ->assertSee('Ketersediaan Data')
        ->assertDontSee('Publikasi')
        ->assertSee('Catatan Kejadian')
        ->assertDontSee('Ambang Batas');
});

it('menu sidebar petugas pos hanya sesuai jenis posnya', function () {
    $this->actingAs(pengguna('petugas_pos', ['pos_id' => 10]))->get('/admin')
        ->assertOk()
        ->assertSee('Duga Air')
        ->assertSee('Debit')
        ->assertDontSee('Unduh Data')
        ->assertDontSee('Hidrometeorologi')
        ->assertDontSee('Publikasi');
});

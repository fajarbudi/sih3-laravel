<?php

use App\Models\ParameterKualitasAir;
use App\Models\PengamatanKualitasAir;
use App\Models\Pos;
use Database\Seeders\ParameterKualitasAirSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new ParameterKualitasAirSeeder)->run();
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 50, 'walidata_id' => 1, 'nama' => 'KA Kali Progo', 'tipe' => 'hidrologi', 'jenis' => 'kualitas_air', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 51, 'walidata_id' => 11, 'nama' => 'KA PJT', 'tipe' => 'hidrologi', 'jenis' => 'kualitas_air', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

function sampelKualitasAir(int $posId, string $waktu, array $atribut = []): PengamatanKualitasAir
{
    return PengamatanKualitasAir::create(['pos_id' => $posId, 'waktu' => $waktu] + $atribut);
}

it('menampilkan kolom seperti aplikasi lama dan hanya data walidata pengguna', function () {
    sampelKualitasAir(50, '2026-11-01', ['lokasi' => 'Desa Srandakan', 'indeks_pencemaran' => 1.2345]);
    sampelKualitasAir(51, '2026-11-01', ['lokasi' => 'Desa Lain', 'indeks_pencemaran' => 9.8765]);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/kualitas-air')
        ->assertOk()
        ->assertSeeInOrder(['Walidata', 'POS', 'Tanggal', 'Lokasi Pengamatan', 'Index Pencemaran', 'Keterangan'])
        ->assertSeeInOrder(['BBWS SERAYU OPAK', 'KA Kali Progo', '1 November 2026', 'Desa Srandakan', '1,2345'])
        ->assertDontSee('9,8765');
});

it('menyaring menurut bulan dan teks pos atau lokasi', function () {
    sampelKualitasAir(50, '2026-10-15', ['lokasi' => 'Hulu', 'indeks_pencemaran' => 1.11]);
    sampelKualitasAir(50, '2026-11-01', ['lokasi' => 'Hilir', 'indeks_pencemaran' => 2.22]);
    sampelKualitasAir(51, '2026-11-01', ['lokasi' => 'Muara', 'indeks_pencemaran' => 3.33]);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/kualitas-air?bulan=2026-11')->assertOk()->assertSee('2,22')->assertSee('3,33')->assertDontSee('1,11');
    $this->get('/admin/kualitas-air?cari=hilir')->assertOk()->assertSee('2,22')->assertDontSee('1,11')->assertDontSee('3,33');
    $this->get('/admin/kualitas-air?cari=pjt')->assertOk()->assertSee('3,33')->assertDontSee('2,22');
});

it('menyimpan sampel beserta nilai parameter, boleh beberapa sampel sehari', function () {
    $this->actingAs($admin = akun('superadmin'));
    $isi = ['pos_id' => 50, 'tanggal' => '2026-11-01', 'lokasi' => 'Desa Srandakan', 'cuaca' => 'cerah',
        'nilai' => ['ph' => '7.1234', 'bod' => '2.5', 'cod' => ''], 'indeks_pencemaran' => '1.75'];

    $this->post('/admin/kualitas-air', $isi)->assertRedirect('/admin/kualitas-air');
    $sampel = PengamatanKualitasAir::sole();
    expect($sampel->waktu->toDateTimeString())->toBe('2026-11-01 00:00:00')
        ->and($sampel->cuaca)->toBe('cerah') // tidak lagi diubah ke "Cerah" seperti ucwords di aplikasi lama
        ->and($sampel->indeks_pencemaran)->toBe(1.75)
        ->and($sampel->diinput_oleh)->toBe($admin->id)
        ->and($sampel->nilaiPerKode())->toEqual(['ph' => 7.1234, 'bod' => 2.5]);

    $this->post('/admin/kualitas-air', $isi)->assertRedirect();
    expect(PengamatanKualitasAir::count())->toBe(2);
});

it('menolak pos lain jenis, angka tidak sah, dan mengabaikan parameter tak dikenal', function () {
    $this->actingAs(akun('superadmin'));
    $isi = ['pos_id' => 50, 'tanggal' => '2026-11-01'];

    $this->post('/admin/kualitas-air', ['pos_id' => 10] + $isi)->assertSessionHasErrors('pos_id');
    $this->post('/admin/kualitas-air', ['nilai' => ['ph' => 'asam']] + $isi)->assertSessionHasErrors('nilai.ph');

    $this->post('/admin/kualitas-air', ['nilai' => ['ph' => '7', 'bukan_parameter' => '5']] + $isi)->assertRedirect();
    expect(PengamatanKualitasAir::sole()->nilaiPerKode())->toEqual(['ph' => 7.0]);
});

it('petugas selalu mencatat untuk posnya sendiri', function () {
    $this->actingAs(akun('petugas_pos', ['pos_id' => 50]));

    $this->post('/admin/kualitas-air', ['pos_id' => 51, 'tanggal' => '2026-10-20', 'nilai' => ['do' => '6.2']])->assertRedirect();

    $sampel = PengamatanKualitasAir::sole();
    expect($sampel->pos_id)->toBe(50)->and($sampel->waktu->toDateString())->toBe('2026-10-20');
});

it('tidak bisa mengubah atau menghapus data pos lain', function () {
    $lain = sampelKualitasAir(51, '2026-11-01');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/kualitas-air/{$lain->id}/ubah")->assertNotFound();
    $this->put("/admin/kualitas-air/{$lain->id}", ['pos_id' => 50, 'tanggal' => '2026-11-01'])->assertNotFound();
    $this->delete("/admin/kualitas-air/{$lain->id}")->assertNotFound();
    expect($lain->fresh())->not->toBeNull();
});

it('mengubah nilai (mengganti yang lama), mempertahankan jam, lalu menghapus beserta nilainya', function () {
    $sampel = sampelKualitasAir(50, '2026-11-01 07:00:00', ['lokasi' => 'Hulu']);
    $sampel->nilai()->create(['parameter_kualitas_air_id' => ParameterKualitasAir::where('kode', 'ph')->value('id'), 'nilai' => 6.5]);
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/kualitas-air/{$sampel->id}/ubah")->assertOk()
        ->assertSeeInOrder(['Ubah Data Kualitas Air', 'KA Kali Progo', 'Hulu', 'Hasil uji', 'pH', 'value="6.5"', 'Index pencemaran', 'Kondisi lapangan', 'Bahan Hanyutan', 'Tervalidasi', 'Simpan perubahan'], false)
        ->assertSee('data-konfirmasi="Data kualitas air KA Kali Progo, 1 November 2026, akan dihapus."', false);

    $this->put("/admin/kualitas-air/{$sampel->id}", ['pos_id' => 50, 'tanggal' => '2026-11-02', 'nilai' => ['ph' => '', 'bod' => '3']])->assertRedirect();
    $sampel->refresh();
    expect($sampel->waktu->toDateTimeString())->toBe('2026-11-02 07:00:00')
        ->and($sampel->nilaiPerKode())->toEqual(['bod' => 3.0])
        ->and($sampel->tervalidasi)->toBeFalse();

    $this->delete("/admin/kualitas-air/{$sampel->id}")->assertRedirect();
    expect($sampel->fresh())->toBeNull()->and(DB::table('nilai_kualitas_air')->count())->toBe(0);
});

it('parameter nonaktif tidak tampil di form dan nilainya tidak hilang saat diubah', function () {
    $sampel = sampelKualitasAir(50, '2026-11-01');
    $sampel->nilai()->create(['parameter_kualitas_air_id' => ParameterKualitasAir::where('kode', 'total_coli')->value('id'), 'nilai' => 0]);
    $this->actingAs(akun('superadmin'));

    $this->get("/admin/kualitas-air/{$sampel->id}/ubah")->assertOk()->assertSee('Total Coliform')->assertDontSee('name="nilai[total_coli]"', false);
    $this->put("/admin/kualitas-air/{$sampel->id}", ['pos_id' => 50, 'tanggal' => '2026-11-01', 'nilai' => ['ph' => '7']])->assertRedirect();

    expect($sampel->fresh()->nilaiPerKode())->toEqual(['total_coli' => 0.0, 'ph' => 7.0]);
});

it('form tambah dan menu sidebar tersedia', function () {
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/kualitas-air/tambah')->assertOk()->assertSee('Tambah Data Kualitas Air')->assertSee('name="nilai[ph]"', false);
    $this->get('/admin')->assertSee(route('admin.kualitas-air'));
});

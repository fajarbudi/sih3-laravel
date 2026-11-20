<?php

use App\Models\BatasSiaga;
use App\Models\PengamatanArr;
use App\Models\PengamatanKualitasAir;
use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use App\Models\TelemetriAwlr;
use App\Models\Walidata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([
        ['id' => 1, 'nama' => 'BBWS SERAYU OPAK', 'urutan' => 1, 'website' => 'https://sda.pu.go.id/balai/bbwsserayuopak/', 'url_ppid' => 'https://ppid.example.go.id', 'whatsapp' => '6281234'],
        ['id' => 3, 'nama' => 'DINAS PUP ESDM DIY', 'urutan' => 2, 'website' => null, 'url_ppid' => null, 'whatsapp' => null],
    ]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'AWLR Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'telemetri', 'latitude' => -7.8, 'longitude' => 110.3]);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 3, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual', 'latitude' => -7.82, 'longitude' => 110.1]);
    Pos::forceCreate(['id' => 30, 'walidata_id' => 1, 'nama' => 'Sumur Tanpa Koordinat', 'tipe' => 'hidrogeologi', 'jenis' => 'mat', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 40, 'walidata_id' => 1, 'nama' => 'Pos Nonaktif', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual', 'aktif' => false, 'latitude' => -7.7, 'longitude' => 110.2]);
});

function dataPeta($respons): Illuminate\Support\Collection
{
    preg_match('/<script type="application\/json" id="data-peta">(.*?)<\/script>/s', $respons->getContent(), $m);

    return collect(json_decode($m[1], true))->keyBy('nama');
}

it('beranda bisa dibuka tanpa login dengan isi beranda lama dan jumlah pos per bidang dari data', function () {
    $this->get('/')->assertOk()
        ->assertSeeInOrder(['<h1', 'Portal', 'SIH3', 'Wilayah Sungai', 'Serayu-Bogowonto', '&amp;', 'Progo-Opak-Serang', '</h1>'], false)
        ->assertSee('Portal publikasi informasi pengelolaan hidrologi, hidrometeorologi, dan hidrogeologi.')
        ->assertSeeInOrder(['role="tablist"', 'Hidrologi', '>1<', 'Hidrometeorologi', '>1<'], false)
        ->assertSee('Hidrologi adalah ilmu yang mempelajari tentang air')
        ->assertSee('1 pos aktif belum memiliki koordinat')
        ->assertDontSee('Pos Nonaktif')
        ->assertSee('Jl. Solo Km. 6, Caturtunggal, Depok, Sleman, D.I. Yogyakarta 55281')
        ->assertSee('Masuk');
});

it('data peta: status dari data telemetri yang masuk, bukan kolom perangkat yang basi', function () {
    PerangkatTelemetri::create(['pos_id' => 10, 'sumber' => 'pjt', 'kode_perangkat' => 'AWLR GLAPAN', 'faktor_skala' => 1, 'aktif' => true,
        'terakhir_diterima_at' => now()->subMonth(), 'nilai_terakhir' => 9]);
    TelemetriAwlr::create(['pos_id' => 10, 'sumber' => 'pjt', 'waktu' => now(config('services.telemetri.zona_waktu'))->subHours(2), 'tma' => 2.5]);
    BatasSiaga::create(['pos_id' => 10, 'level' => 2, 'nilai' => 2.0]);
    BatasSiaga::create(['pos_id' => 10, 'level' => 3, 'nilai' => 3.0]);

    $titik = dataPeta($this->get('/')->assertOk());

    expect($titik['AWLR Glapan'])->toMatchArray(['kel' => 'awlr', 'jenis' => 'Duga air', 'nilai' => '2,5 m', 'status' => 'online',
        'kelas' => 2, 'kelasLabel' => 'Waspada', 'wid' => 1, 'walidata' => 'BBWS SERAYU OPAK']);
});

it('pos telemetri yang tidak mengirim > 24 jam tidak punya kelas peringatan; pos manual memakai pengamatan terakhir', function () {
    PerangkatTelemetri::create(['pos_id' => 10, 'sumber' => 'pjt', 'kode_perangkat' => 'AWLR GLAPAN', 'faktor_skala' => 1, 'aktif' => true]);
    TelemetriAwlr::create(['pos_id' => 10, 'sumber' => 'pjt', 'waktu' => now()->subDays(4), 'tma' => 1.0]);
    TelemetriAwlr::create(['pos_id' => 10, 'sumber' => 'pjt', 'waktu' => now()->subDays(3), 'tma' => 2.5]);
    BatasSiaga::create(['pos_id' => 10, 'level' => 2, 'nilai' => 2.0]);
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2026-10-01 07:00:00', 'hujan' => 12.5]);
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2026-11-01 07:00:00', 'hujan' => 3.25]);

    $titik = dataPeta($this->get('/')->assertOk()->assertSee('Pos peringatan <span class="tabular-nums">(0)</span>', false));

    expect($titik->keys()->all())->toBe(['ARR Sermo', 'AWLR Glapan'])
        ->and($titik['AWLR Glapan'])->toMatchArray(['nilai' => '2,5 m', 'status' => 'offline', 'kelas' => null])
        ->and($titik['ARR Sermo'])->toMatchArray(['kel' => 'arr', 'label' => 'Curah hujan', 'nilai' => '3,25 mm', 'status' => 'manual', 'kelas' => null, 'hujan24' => null]);
});

it('kelas hujan dari total 24 jam telemetri dan kelas kualitas air dari indeks pencemaran', function () {
    Pos::forceCreate(['id' => 21, 'walidata_id' => 1, 'nama' => 'ARR Telemetri', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'telemetri', 'latitude' => -7.6, 'longitude' => 110.0]);
    Pos::forceCreate(['id' => 22, 'walidata_id' => 1, 'nama' => 'KA Progo', 'tipe' => 'hidrologi', 'jenis' => 'kualitas_air', 'metode' => 'manual', 'latitude' => -7.6, 'longitude' => 110.1]);
    PerangkatTelemetri::create(['pos_id' => 21, 'sumber' => 'pjt', 'kode_perangkat' => 'ARR T', 'faktor_skala' => 1, 'aktif' => true]);
    $jam = now(config('services.telemetri.zona_waktu'))->startOfHour();
    DB::table('telemetri_arr')->insert(['pos_id' => 21, 'sumber' => 'pjt', 'waktu' => $jam->toDateTimeString(), 'hujan' => 4]);
    DB::table('rekap_arr_per_jam')->insert([
        ['pos_id' => 21, 'jam' => $jam->copy()->subHours(2)->toDateTimeString(), 'hujan' => 8, 'jumlah_data' => 1],
        ['pos_id' => 21, 'jam' => $jam->toDateTimeString(), 'hujan' => 4, 'jumlah_data' => 1],
        ['pos_id' => 21, 'jam' => $jam->copy()->subDays(2)->toDateTimeString(), 'hujan' => 50, 'jumlah_data' => 1], // di luar 24 jam
    ]);
    PengamatanKualitasAir::create(['pos_id' => 22, 'waktu' => '2026-09-01 09:00:00', 'indeks_pencemaran' => 6.2]);

    $titik = dataPeta($this->get('/')->assertOk()->assertSee('Pos peringatan <span class="tabular-nums">(2)</span>', false));

    expect($titik['ARR Telemetri'])->toMatchArray(['hujan24' => '12 mm', 'kelas' => 5, 'kelasLabel' => 'Hujan lebat'])
        ->and($titik['KA Progo'])->toMatchArray(['jenis' => 'Kualitas air', 'kelas' => 3, 'kelasLabel' => 'Cemar sedang']);
});

it('rincian bidang per walidata per jenis', function () {
    Pos::forceCreate(['id' => 22, 'walidata_id' => 1, 'nama' => 'KA Progo', 'tipe' => 'hidrologi', 'jenis' => 'kualitas_air', 'metode' => 'manual']);

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['id="bidang-hidrologi"', 'Duga air: 1', 'Kualitas air: 1'], false)
        ->assertSeeInOrder(['id="bidang-hidrologi"', '2', 'pos dari 1 instansi'], false);
});

it('nama pos tidak bisa menyisipkan HTML ke data peta', function () {
    Pos::forceCreate(['id' => 50, 'walidata_id' => 1, 'nama' => '</script><script>alert(1)</script>', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual', 'latitude' => -7.9, 'longitude' => 110.4]);

    $this->get('/')->assertOk()->assertDontSee('</script><script>alert(1)', false);
});

it('walidata: logo, jumlah pos aktif, dan hanya tautan yang ada', function () {
    $this->get('/')->assertOk()
        ->assertSee('href="https://wa.me/6281234"', false)
        ->assertSee('href="https://ppid.example.go.id"', false)
        ->assertSee('src="'.asset('images/walidata/1.png').'"', false)
        ->assertSeeInOrder(['Walidata SIH3', 'BBWS SERAYU OPAK', 'DINAS PUP ESDM DIY'])
        ->assertSee('href="https://delineasi.bbwsserayuopak.id/"', false)
        ->assertSee('href="https://flood-routing-bbwsso.vercel.app"', false);
});

it('menu walidata diperbarui segera setelah walidata diubah', function () {
    $this->get('/')->assertDontSee('DLHK BARU');
    Walidata::create(['nama' => 'DLHK BARU', 'urutan' => 3]);

    $this->get('/')->assertSee('DLHK BARU');
});

it('menu walidata tetap tampil saat dibaca dari cache yang diserialisasi (driver file seperti server)', function () {
    config(['cache.default' => 'file']);
    Cache::forget('portal.menu-walidata');

    $this->get('/')->assertOk()->assertSee('BBWS SERAYU OPAK');
    $this->get('/')->assertOk()->assertSee('href="https://ppid.example.go.id"', false); // kedua: dari cache

    Cache::forget('portal.menu-walidata');
});

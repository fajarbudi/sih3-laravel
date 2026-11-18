<?php

use App\Models\LogTelemetri;
use App\Models\ParameterInstrumen;
use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use App\Services\RekapTelemetri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.telemetri.zona_waktu' => 'Asia/Jakarta']);
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    // Query rekap memakai fungsi khusus MySQL, sedangkan test berjalan di SQLite.
    $this->mock(RekapTelemetri::class)->shouldReceive('hitungUlangArr', 'hitungUlangAwlr')->andReturn(1);
    $this->travelTo(Carbon::parse('2026-11-19 01:30', 'Asia/Jakarta'));
});

function buatPerangkat(int $posId, string $jenis, string $sumber, string $kode, ?string $sensor = null): PerangkatTelemetri
{
    Pos::find($posId) ?? Pos::forceCreate(['id' => $posId, 'walidata_id' => 1, 'nama' => "Pos {$posId}", 'tipe' => 'hidrologi', 'jenis' => $jenis, 'metode' => 'telemetri']);

    return PerangkatTelemetri::create(['pos_id' => $posId, 'sumber' => $sumber, 'kode_perangkat' => $kode, 'sensor' => $sensor]);
}

function nilaiTabel(string $tabel, string $kolom): array
{
    return DB::table($tabel)->orderBy('pos_id')->orderBy('waktu')->get()
        ->map(fn ($r) => [$r->pos_id, $r->waktu, (float) $r->$kolom])->all();
}

it('menolak sumber yang tidak dikenal', function () {
    $this->artisan('telemetri:fetch', ['sumber' => 'xyz'])->assertExitCode(2);
});

it('pcb-jogja: menyimpan nilai terakhir dan tidak menggandakan saat diulang', function () {
    buatPerangkat(1900, 'awlr', 'login', 'SOWL024');
    Http::fake(['*' => Http::response([
        ['id' => 'SOWL024', 'last_online' => '2026-11-19 01:28:01', 'status' => ['water_level' => 0.05]],
        ['id' => 'SOWL099', 'last_online' => '2026-11-19 01:28:01', 'status' => ['water_level' => 1]],
    ])]);

    $this->artisan('telemetri:fetch', ['sumber' => 'pcb-jogja'])->assertSuccessful();
    $this->artisan('telemetri:fetch', ['sumber' => 'pcb-jogja'])->assertSuccessful();

    expect(nilaiTabel('telemetri_awlr', 'tma'))->toBe([[1900, '2026-11-19 01:28:01', 0.05]]);
    expect(PerangkatTelemetri::first()->nilai_terakhir)->toBe(0.05);
});

it('stesy: mengambil parameter muka air tanah per logger', function () {
    buatPerangkat(1903, 'mat', 'stesy', '10366');
    buatPerangkat(1905, 'mat', 'stesy', '10392');
    Http::fake(['*' => Http::response(['data' => [
        ['id_logger' => 10366, 'waktu' => '2026-11-19 01:30:00', 'data' => [['nama_parameter' => 'Muka_Air_Tanah', 'nilai' => 10.84]]],
        ['id_logger' => 10392, 'waktu' => '2026-11-19 01:30:00', 'data' => [['nama_parameter' => 'Baterai', 'nilai' => 12]]],
    ]])]);

    $this->artisan('telemetri:fetch', ['sumber' => 'stesy'])->assertSuccessful();

    // Logger tanpa parameter muka air tanah dilewati (kode lama menyimpan nilai logger sebelumnya).
    expect(nilaiTabel('telemetri_awlr', 'tma'))->toBe([[1903, '2026-11-19 01:30:00', 10.84]]);
});

it('higertech: rainfall ke pos ARR, water_level ke pos AWLR, mulai dari tanggal data terakhir', function () {
    buatPerangkat(1729, 'awlr', 'higertech', 'HGT1465');
    buatPerangkat(1730, 'arr', 'higertech', 'HGT1465');
    DB::table('telemetri_arr')->insert(['pos_id' => 1730, 'sumber' => 'higertech', 'waktu' => '2026-11-18 23:50:00', 'hujan' => 0]);
    Http::fake(['*' => Http::response(['response' => [
        ['reading_at' => '2026-11-19 00:00:00', 'rainfall' => 0.5, 'water_level' => 3.39],
    ]])]);

    $this->artisan('telemetri:fetch', ['sumber' => 'higertech'])->assertSuccessful();

    expect(DB::table('telemetri_arr')->where('waktu', '2026-11-19 00:00:00')->value('hujan'))->toEqual(0.5)
        ->and(DB::table('telemetri_awlr')->value('tma'))->toEqual(3.39);
    Http::assertSent(fn (Request $r) => $r['from'] === '2026-11-18' && $r['to'] === '2026-11-19');
});

it('tatonas: memetakan kolom history per jenis pos dan mencatat logger yang gagal', function () {
    buatPerangkat(1734, 'awlr', 'tatonas', '4105');
    buatPerangkat(1735, 'klimatologi', 'tatonas', '4106');
    buatPerangkat(1733, 'arr', 'tatonas', '4104');
    Http::fake(fn (Request $r) => match ($r['hw']) {
        '4105' => Http::response(['statusCode' => 200, 'data' => ['detail' => [['tanggal' => '2026-11-19 01:00:00', 'waterlevel' => 137.36, 'debitair' => 2.15]]]]),
        '4106' => Http::response(['statusCode' => 200, 'data' => ['detail' => [['tanggal' => '2026-11-19 01:00:00', 'airhumidity' => 84.9, 'panlevel' => 3.8, 'rainfall' => 0]]]]),
        default => Http::response(['statusCode' => 400, 'message' => 'Range tanggal tidak boleh lebih dari 31 hari!'], 400),
    });

    $this->artisan('telemetri:fetch', ['sumber' => 'tatonas'])->expectsOutputToContain('1 gagal')->assertSuccessful();

    $awlr = DB::table('telemetri_awlr')->sole();
    $klim = DB::table('telemetri_klimatologi')->sole();
    expect([(float) $awlr->tma, (float) $awlr->debit])->toBe([137.36, 2.15])
        ->and([(float) $klim->kelembapan, (float) $klim->penguapan, (float) $klim->hujan])->toBe([84.9, 3.8, 0.0])
        ->and(LogTelemetri::sole()->kode_perangkat)->toBe('4104');
});

it('pjt: mencocokkan stasiun per jenis, membuat pos baru, dan login ulang saat token ditolak', function () {
    config(['services.pjt.url' => 'https://pjt.test/api', 'services.pjt.walidata_id' => 11]);
    buatPerangkat(1454, 'arr', 'pjt', 'BENDUNG BANDUNG');
    buatPerangkat(1475, 'awlr', 'pjt', 'BENDUNG BANDUNG');

    $ditolak = false;
    Http::fake(function (Request $r) use (&$ditolak) {
        if (str_ends_with($r->url(), '/login')) {
            return Http::response(['token' => 'token-'.uniqid()]);
        }
        if (! $ditolak) { // permintaan pertama: token dianggap kedaluwarsa
            $ditolak = true;

            return Http::response(['message' => 'Unauthenticated.'], 401);
        }

        return str_contains($r->url(), '/arr/')
            ? Http::response([['data' => ['nama_station' => 'BENDUNG BANDUNG', 'datetime' => '2026-11-19 01:00:00', 'hourly_rf' => 2.5, 'cumulative' => 12.4]]])
            : Http::response([
                ['data' => ['nama_station' => 'BENDUNG BANDUNG', 'datetime' => '2026-11-19 01:03:45', 'tma' => 171.88, 'level' => 0]],
                ['access' => ['lat' => '-7,65', 'long' => '110,43'], 'data' => ['nama_station' => 'POS BARU', 'datetime' => '2026-11-19 01:00:00', 'tma' => 50]],
            ]);
    });

    $this->artisan('telemetri:fetch', ['sumber' => 'pjt'])->assertSuccessful();

    expect(nilaiTabel('telemetri_arr', 'hujan'))->toBe([[1454, '2026-11-19 01:00:00', 2.5]])
        ->and(DB::table('telemetri_awlr')->where('pos_id', 1475)->value('tma'))->toEqual(171.88);
    $baru = PerangkatTelemetri::where('kode_perangkat', 'POS BARU')->sole();
    expect([$baru->faktor_skala, $baru->pos->walidata_id, $baru->pos->latitude])->toBe([0.01, 11, -7.65]);
});

it('beacon: memakai Basic Auth dan menimpa nilai jam yang masih berjalan', function () {
    config(['services.beacon.username' => 'admin', 'services.beacon.password' => 'rahasia']);
    buatPerangkat(1147, 'arr', 'beacon', '10053', 'sensor9');

    $hujan = 0.5;
    Http::fake(function () use (&$hujan) {
        return Http::response(['status' => true, 'data' => [
            ['waktu' => '2026-11-19 01:00:00', 'data' => [['nama_parameter' => 'Curah_Hujan', 'nilai' => $hujan], ['nama_parameter' => 'Baterai', 'nilai' => 12]]],
        ]]);
    });

    $this->artisan('telemetri:fetch', ['sumber' => 'beacon'])->assertSuccessful();
    $hujan = 2.0; // jam 01 belum selesai, API mengembalikan jumlah yang lebih besar
    $this->artisan('telemetri:fetch', ['sumber' => 'beacon'])->assertSuccessful();

    expect(nilaiTabel('telemetri_arr', 'hujan'))->toBe([[1147, '2026-11-19 01:00:00', 2.0]]);
    Http::assertSent(fn (Request $r) => $r['id_logger'] === '10053' && $r['interval'] === 'jam'
        && $r->hasHeader('Authorization', 'Basic '.base64_encode('admin:rahasia')));
});

it('sermo: mendaftarkan perangkat, mengubah UTC ke WIB, membuang nilai error, satu bacaan per 5 menit', function () {
    config(['services.sermo.token' => 'uji', 'services.sermo.walidata_id' => 1]);
    buatPerangkat(1900, 'awlr', 'login', 'SOWL024'); // Ngrancah sudah ada lewat PCB Jogja

    $titik = fn ($ts, $v, $err = null) => ['timestamp' => $ts, 'value' => $v, 'value_error' => $err];
    Http::fake(function (Request $r) use ($titik) {
        if (str_contains($r->url(), 'get-lastvalue')) {
            return Http::response("\xEF\xBB\xBF".json_encode([ // respons Sermo diawali BOM
                ['device' => 'SOWL001', 'tipesensor' => 'awlr', 'params' => [
                    'tma1' => ['group' => 'waduk', 'satuan' => 'mdpl', 'keterangan' => 'AWLR intake'],
                    'vbat' => ['satuan' => 'V'], // diagnostik logger, tanpa group
                ]],
                ['device' => 'SOWL024', 'tipesensor' => 'awlr', 'params' => ['tma' => ['group' => 'hulu', 'satuan' => 'm']]],
                ['device' => 'SOKM010', 'tipesensor' => 'klimat', 'params' => []],
                ['device' => 'SOVN019', 'tipesensor' => 'vno', 'params' => ['Vnoq' => ['group' => 'vno', 'satuan' => 'L/s', 'label' => 'Vnotch']]],
                ['device' => 'SOGN001', 'tipesensor' => 'genset', 'params' => ['kwh_p' => ['group' => 'genset1']]],
            ]));
        }

        return Http::response(json_encode([['params' => match ($r['device']) {
            'SOWL001' => ['tma1' => ['data' => [
                $titik('2026-11-18T17:00:00Z', 128.06),
                $titik('2026-11-18T17:00:20Z', 128.07),        // slot 5 menit yang sama -> dilewati
                $titik('2026-11-18T17:05:00Z', -999, 'error'), // nilai error -> dilewati
            ]]],
            'SOWL024' => ['tma' => ['data' => [$titik('2026-11-18T17:00:00Z', 0.05)]]],
            'SOKM010' => ['curah_hujan' => ['data' => [$titik('2026-11-18T17:00:00Z', 0.02)]], 'temperature_udara' => ['data' => [$titik('2026-11-18T17:00:00Z', 25.6)]]],
            'SOVN019' => ['Vnoq' => ['data' => [$titik('2026-11-18T17:00:00Z', 0.03)]]],
        }]]));
    });

    $this->artisan('telemetri:fetch', ['sumber' => 'sermo'])->assertSuccessful();
    $this->artisan('telemetri:fetch', ['sumber' => 'sermo'])->assertSuccessful();

    $intake = PerangkatTelemetri::where(['sumber' => 'sermo', 'kode_perangkat' => 'SOWL001'])->sole();
    expect($intake->pos->nama)->toBe('AWLR intake')
        ->and(PerangkatTelemetri::where(['sumber' => 'sermo', 'kode_perangkat' => 'SOWL024'])->value('pos_id'))->toBe(1900)
        ->and(ParameterInstrumen::pluck('kode_parameter')->all())->toBe(['Vnoq'])
        ->and(Pos::count())->toBe(3); // 1900 + intake + klimatologi; genset tidak dibuatkan pos

    expect(nilaiTabel('telemetri_awlr', 'tma'))->toBe([[1900, '2026-11-19 00:00:00', 0.05], [$intake->pos_id, '2026-11-19 00:00:00', 128.06]]);
    $klim = DB::table('telemetri_klimatologi')->sole();
    expect([(float) $klim->hujan, (float) $klim->suhu])->toBe([0.02, 25.6])
        ->and(DB::table('telemetri_instrumen')->value('nilai'))->toEqual(0.03);
});

it('mencatat kegagalan yang tidak terduga ke log_telemetri', function () {
    config(['services.pjt.url' => 'https://pjt.test/api']);
    Http::fake(['*' => Http::response([], 500)]); // login PJT gagal

    $this->artisan('telemetri:fetch', ['sumber' => 'pjt'])->assertFailed();

    expect(LogTelemetri::sole())->sumber->toBe('pjt')->pesan->toBe('Login PJT gagal');
});

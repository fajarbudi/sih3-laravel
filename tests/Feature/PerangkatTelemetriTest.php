<?php

use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK']]);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'Karangtalun', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'telemetri']);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'telemetri']);
});

it('khusus superadmin', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/perangkat-telemetri')->assertForbidden();
    $this->actingAs(akun('petugas_pos', ['pos_id' => 10, 'username' => 'p']))->post('/admin/perangkat-telemetri', [])->assertForbidden();
});

it('menampilkan perangkat semua sumber beserta status data terakhir', function () {
    PerangkatTelemetri::create(['pos_id' => 20, 'sumber' => 'beacon', 'kode_perangkat' => '10044', 'sensor' => 'sensor1', 'faktor_skala' => 1, 'aktif' => true,
        'terakhir_diterima_at' => now()->subDays(3), 'nilai_terakhir' => 2.5]);
    PerangkatTelemetri::create(['pos_id' => 10, 'sumber' => 'tatonas', 'kode_perangkat' => '4105', 'faktor_skala' => 0.01, 'aktif' => false]);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/perangkat-telemetri')->assertOk()
        ->assertSeeInOrder(['Sumber', 'Kode Perangkat', 'Sensor', 'POS', 'Faktor Skala', 'Status', 'Data Terakhir'])
        ->assertSeeInOrder(['Beacon', '10044', 'sensor1', 'Karangtalun', 'Aktif', 'Lebih dari 1 hari', 'nilai 2,5'])
        ->assertSeeInOrder(['Tatonas', '4105', 'Bendung Glapan', '0,01', 'Nonaktif', 'Belum ada']);

    $this->get('/admin/perangkat-telemetri?sumber=tatonas')->assertSee('4105')->assertDontSee('10044');
    $this->get('/admin/perangkat-telemetri?cari=karang')->assertSee('10044')->assertDontSee('4105');
    $this->get('/admin')->assertSee(route('admin.perangkat-telemetri'));
});

it('menambah, mengubah, dan menghapus pemetaan; duplikat ke pos yang sama ditolak', function () {
    $this->actingAs(akun('superadmin'));
    $isi = ['sumber' => 'beacon', 'kode_perangkat' => '10050', 'pos_id' => 20, 'sensor' => 'sensor9', 'faktor_skala' => '1', 'aktif' => '1'];

    $this->post('/admin/perangkat-telemetri', $isi)->assertRedirect('/admin/perangkat-telemetri');
    $p = PerangkatTelemetri::sole();
    expect($p->aktif)->toBeTrue()->and($p->sensor)->toBe('sensor9');

    $this->post('/admin/perangkat-telemetri', $isi)->assertSessionHasErrors('kode_perangkat');
    $this->post('/admin/perangkat-telemetri', ['pos_id' => 10] + $isi)->assertRedirect(); // perangkat sama ke pos lain boleh
    $this->post('/admin/perangkat-telemetri', ['sumber' => 'tidak-ada', 'faktor_skala' => '0'] + $isi)->assertSessionHasErrors(['sumber', 'faktor_skala']);

    $this->get("/admin/perangkat-telemetri/{$p->id}/ubah")->assertOk()->assertSee('value="10050"', false);
    $this->put("/admin/perangkat-telemetri/{$p->id}", ['faktor_skala' => '0.01', 'aktif' => null] + $isi)->assertRedirect();
    expect($p->fresh())->faktor_skala->toBe(0.01)->aktif->toBeFalse();

    $this->delete("/admin/perangkat-telemetri/{$p->id}")->assertRedirect();
    expect(PerangkatTelemetri::count())->toBe(1);
});

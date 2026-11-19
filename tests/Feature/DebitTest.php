<?php

use App\Models\PengamatanDebit;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 11, 'walidata_id' => 11, 'nama' => 'Bendung PJT', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
});

function debit(int $posId, string $waktu, float $debit = 2.5): PengamatanDebit
{
    return PengamatanDebit::create(['pos_id' => $posId, 'waktu' => $waktu, 'debit' => $debit]);
}

it('admin walidata hanya melihat data debit walidatanya, dengan kolom seperti aplikasi lama', function () {
    debit(10, '2026-11-01 07:00:00', 12.345);
    debit(11, '2026-11-01 07:00:00', 98.765);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/debit')
        ->assertOk()
        ->assertSeeInOrder(['Debit Intake Kanan (m3/s)', 'Debit Intake Kiri (m3/s)', 'Debit Limpas (m3/s)', 'Debit (m3/s)', 'Keterangan'])
        ->assertSee('12,345')
        ->assertDontSee('98,765');
});

it('menyaring menurut pos, jam, dan bulan', function () {
    debit(10, '2026-10-31 17:00:00', 1.11);
    debit(10, '2026-11-01 07:00:00', 2.22);
    debit(10, '2026-11-01 12:00:00', 4.44);
    debit(11, '2026-11-01 07:00:00', 3.33);

    $this->actingAs(akun('superadmin'))->get('/admin/debit?pos=10&bulan=2026-11&jam=7')
        ->assertOk()
        ->assertSee('2,22')
        ->assertDontSee('1,11')
        ->assertDontSee('3,33')
        ->assertDontSee('4,44');
});

it('menambah data; pos non-AWLR dan duplikat jam ditolak', function () {
    $this->actingAs($admin = akun('superadmin'));
    $isi = ['pos_id' => 10, 'tanggal' => '2026-11-01', 'jam' => 12, 'intake_kanan' => '1.25', 'intake_kiri' => '0.75', 'limpas' => '6440', 'debit' => '6442.125'];

    $this->post('/admin/debit', $isi)->assertRedirect('/admin/debit');
    $data = PengamatanDebit::sole();
    expect($data->waktu->toDateTimeString())->toBe('2026-11-01 12:00:00')
        ->and($data->intake_kanan)->toBe(1.25)
        ->and($data->limpas)->toBe(6440.0)
        ->and($data->debit)->toBe(6442.125)
        ->and($data->diinput_oleh)->toBe($admin->id);

    $this->post('/admin/debit', $isi)->assertSessionHasErrors('jam');
    $this->post('/admin/debit', ['pos_id' => 20] + $isi)->assertSessionHasErrors('pos_id');
    $this->post('/admin/debit', ['debit' => 'deras', 'jam' => 7] + $isi)->assertSessionHasErrors('debit');
});

it('petugas mencatat untuk posnya sendiri pada hari ini', function () {
    $this->actingAs(akun('petugas_pos', ['pos_id' => 10]));

    $this->post('/admin/debit', ['pos_id' => 11, 'tanggal' => '2020-01-01', 'jam' => 7, 'debit' => '0.8'])->assertRedirect();

    $data = PengamatanDebit::sole();
    expect($data->pos_id)->toBe(10)
        ->and($data->waktu->toDateTimeString())->toBe(today()->setHour(7)->toDateTimeString());
});

it('tidak bisa mengubah atau menghapus data pos lain', function () {
    $lain = debit(11, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/debit/{$lain->id}/ubah")->assertNotFound();
    $this->put("/admin/debit/{$lain->id}", ['pos_id' => 10, 'tanggal' => '2026-11-01', 'jam' => 7])->assertNotFound();
    $this->delete("/admin/debit/{$lain->id}")->assertNotFound();
    expect($lain->fresh())->not->toBeNull();
});

it('mengubah, mencabut validasi, dan menghapus data', function () {
    $data = debit(10, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/debit/{$data->id}/ubah")->assertOk()
        ->assertSeeInOrder(['Debit', 'Ubah data', 'Bendung Glapan', '2026-11-01', 'Intake kanan', 'Tervalidasi', 'Hapus', 'Simpan perubahan'])
        ->assertSee('step="0.001"', false)
        ->assertSee('data-konfirmasi="Data debit Bendung Glapan', false);
    $this->put("/admin/debit/{$data->id}", ['pos_id' => 10, 'tanggal' => '2026-11-01', 'jam' => 7, 'debit' => '3.875'])->assertRedirect();
    expect($data->fresh())->debit->toBe(3.875)->tervalidasi->toBeFalse();

    $this->delete("/admin/debit/{$data->id}")->assertRedirect();
    expect($data->fresh())->toBeNull();
});

it('form tambah tersedia; menu Debit hanya untuk petugas seperti aplikasi lama', function () {
    $this->actingAs(akun('superadmin'))->get('/admin/debit/tambah')->assertOk()->assertSee('Tambah Data Debit');

    // Di aplikasi lama menu Debit admin dikomentari; hanya menu petugas pos yang memuatnya.
    $this->actingAs(akun('petugas_pos', ['pos_id' => 10]))->get('/admin')->assertSee(route('admin.debit'));
});

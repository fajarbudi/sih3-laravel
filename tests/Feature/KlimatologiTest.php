<?php

use App\Models\PengamatanKlimatologi;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 30, 'walidata_id' => 1, 'nama' => 'Klimat Bantul', 'tipe' => 'hidrometeorologi', 'jenis' => 'klimatologi', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 31, 'walidata_id' => 11, 'nama' => 'Klimat PJT', 'tipe' => 'hidrometeorologi', 'jenis' => 'klimatologi', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
});

function klimatologi(int $posId, string $waktu, array $nilai = []): PengamatanKlimatologi
{
    return PengamatanKlimatologi::create(['pos_id' => $posId, 'waktu' => $waktu] + $nilai);
}

it('admin walidata hanya melihat data klimatologi walidatanya, dengan kolom seperti aplikasi lama', function () {
    klimatologi(30, '2026-11-01 07:00:00', ['kelembapan' => 81.5, 'suhu_max' => 31.25]);
    klimatologi(31, '2026-11-01 07:00:00', ['kelembapan' => 66.6]);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/klimatologi')
        ->assertOk()
        ->assertSeeInOrder(['RH', 'Temperatur Maksimal', 'Temperatur Minimal', 'Penguapan', 'Lama Penyinaran', 'Hujan', 'Keterangan'])
        ->assertSee('81,5')
        ->assertSee('31,25')
        ->assertDontSee('66,6');
});

it('menyaring menurut pos, jam, dan bulan', function () {
    klimatologi(30, '2026-10-31 17:00:00', ['hujan' => 1.11]);
    klimatologi(30, '2026-11-01 07:00:00', ['hujan' => 2.22]);
    klimatologi(30, '2026-11-01 12:00:00', ['hujan' => 4.44]);
    klimatologi(31, '2026-11-01 07:00:00', ['hujan' => 3.33]);

    $this->actingAs(akun('superadmin'))->get('/admin/klimatologi?pos=30&bulan=2026-11&jam=7')
        ->assertOk()
        ->assertSee('2,22')
        ->assertDontSee('1,11')
        ->assertDontSee('3,33')
        ->assertDontSee('4,44');
});

it('menambah data; pos non-klimatologi, angka tidak sah, dan duplikat jam ditolak', function () {
    $this->actingAs($admin = akun('superadmin'));
    $isi = ['pos_id' => 30, 'tanggal' => '2026-11-01', 'jam' => 7, 'kelembapan' => '85', 'suhu_min' => '22.4', 'radiasi' => '350'];

    $this->post('/admin/klimatologi', $isi)->assertRedirect('/admin/klimatologi');
    $data = PengamatanKlimatologi::sole();
    expect($data->waktu->toDateTimeString())->toBe('2026-11-01 07:00:00')
        ->and($data->kelembapan)->toBe(85.0)
        ->and($data->suhu_min)->toBe(22.4)
        ->and($data->penguapan)->toBeNull()
        ->and($data->diinput_oleh)->toBe($admin->id);

    $this->post('/admin/klimatologi', $isi)->assertSessionHasErrors('jam');
    $this->post('/admin/klimatologi', ['pos_id' => 20] + $isi)->assertSessionHasErrors('pos_id');
    $this->post('/admin/klimatologi', ['suhu_max' => 'panas', 'jam' => 12] + $isi)->assertSessionHasErrors('suhu_max');
});

it('petugas mencatat untuk posnya sendiri pada hari ini', function () {
    $this->actingAs(akun('petugas_pos', ['pos_id' => 30]));

    $this->post('/admin/klimatologi', ['pos_id' => 31, 'tanggal' => '2020-01-01', 'jam' => 7, 'hujan' => '0'])->assertRedirect();

    $data = PengamatanKlimatologi::sole();
    expect($data->pos_id)->toBe(30)
        ->and($data->waktu->toDateTimeString())->toBe(today()->setHour(7)->toDateTimeString());
});

it('tidak bisa mengubah atau menghapus data pos lain', function () {
    $lain = klimatologi(31, '2026-11-01 07:00:00');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/klimatologi/{$lain->id}/ubah")->assertNotFound();
    $this->put("/admin/klimatologi/{$lain->id}", ['pos_id' => 30, 'tanggal' => '2026-11-01', 'jam' => 7])->assertNotFound();
    $this->delete("/admin/klimatologi/{$lain->id}")->assertNotFound();
    expect($lain->fresh())->not->toBeNull();
});

it('mengubah, mencabut validasi, dan menghapus data', function () {
    $data = klimatologi(30, '2026-11-01 07:00:00', ['hujan' => 5]);
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get("/admin/klimatologi/{$data->id}/ubah")->assertOk()
        ->assertSeeInOrder(['Klimatologi', 'Ubah data', 'Klimat Bantul', '2026-11-01', 'Suhu dan kelembapan udara', 'Angin dan matahari', 'Tervalidasi', 'Hapus', 'Simpan perubahan'])
        ->assertSee('data-konfirmasi="Data klimatologi Klimat Bantul', false);
    $this->put("/admin/klimatologi/{$data->id}", ['pos_id' => 30, 'tanggal' => '2026-11-01', 'jam' => 7, 'penguapan' => '4.75', 'hujan' => ''])->assertRedirect();
    expect($data->fresh())->penguapan->toBe(4.75)->hujan->toBeNull()->tervalidasi->toBeFalse();

    $this->delete("/admin/klimatologi/{$data->id}")->assertRedirect();
    expect($data->fresh())->toBeNull();
});

it('form tambah dan menu sidebar tersedia', function () {
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/klimatologi/tambah')->assertOk()->assertSee('Tambah Data Klimatologi');
    $this->get('/admin')->assertSee(route('admin.klimatologi'));
});

it('menerima bacaan counter anemometer yang besar tanpa terpotong', function () {
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/klimatologi', ['pos_id' => 30, 'tanggal' => '2026-11-01', 'jam' => 7, 'kecepatan_angin' => '2158290'])->assertRedirect();

    expect(PengamatanKlimatologi::sole()->kecepatan_angin)->toBe(2158290.0);
});

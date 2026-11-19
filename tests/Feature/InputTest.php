<?php

use App\Models\ArsipPos;
use App\Models\PengamatanArr;
use App\Models\PengamatanDebit;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK']]);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

// Tanggal, Jan..Des dipisah tab (seperti salinan dari Excel); baris judul diabaikan.
const TEMPEL = "Tgl\tJan\tFeb\tMar\n1\t12,5\t0\t-\n2\t\t3.25\tx\n30\t1\t9\t2\n";

it('pratinjau menampilkan grid dan melaporkan tanggal yang tidak ada', function () {
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/input/curah-hujan')->assertOk()->assertSee('Input Curah Hujan')->assertSee('ARR Sermo')->assertDontSee('Bendung Glapan');
    $this->post('/admin/input/curah-hujan/pratinjau', ['pos_id' => 20, 'tahun' => 2025, 'tempel' => TEMPEL])->assertOk()
        ->assertSee('5 nilai akan disimpan')
        ->assertSee('Dilewati karena tanggal tidak ada di tahun 2025: 30/2')
        ->assertSee('Simpan 5 data');
    expect(PengamatanArr::count())->toBe(0);
});

it('simpan curah hujan: jam 07, nilai 0 ikut disimpan, data lama diperbarui, PDF arsip dibuat', function () {
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2025-01-01 07:00:00', 'hujan' => 99]);
    $this->actingAs($admin = akun('superadmin'));

    $this->post('/admin/input/curah-hujan', ['pos_id' => 20, 'tahun' => 2025, 'tempel' => TEMPEL])->assertRedirect('/admin/input/curah-hujan');

    $data = PengamatanArr::orderBy('waktu')->get();
    expect($data)->toHaveCount(5)
        ->and($data->first()->waktu->toDateTimeString())->toBe('2025-01-01 07:00:00')
        ->and($data->first()->hujan)->toBe(12.5)
        ->and($data->first()->diinput_oleh)->toBe($admin->id)
        ->and(PengamatanArr::where('waktu', '2025-02-01 07:00:00')->value('hujan'))->toBe(0.0)
        ->and(PengamatanArr::where('waktu', '2025-02-02 07:00:00')->value('hujan'))->toBe(3.25);
    expect(Storage::disk('public')->get(ArsipPos::where(['pos_id' => 20, 'tahun' => 2025])->sole()->path))->toStartWith('%PDF');
});

it('simpan debit ke pengamatan_debit untuk pos AWLR', function () {
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/input/debit', ['pos_id' => 10, 'tahun' => 2025, 'tempel' => "5\t1,75\t2"])->assertRedirect('/admin/input/debit');

    expect(PengamatanDebit::where('waktu', '2025-01-05 07:00:00')->value('debit'))->toBe(1.75)
        ->and(PengamatanDebit::count())->toBe(2)
        ->and(ArsipPos::count())->toBe(0);
});

it('menolak pos salah jenis dan khusus superadmin', function () {
    $this->actingAs(akun('superadmin'))->post('/admin/input/curah-hujan/pratinjau', ['pos_id' => 10, 'tahun' => 2025, 'tempel' => TEMPEL])->assertSessionHasErrors('pos_id');
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/input/curah-hujan')->assertForbidden();
    $this->actingAs(akun('superadmin', ['username' => 'su']))->get('/admin')
        ->assertSee(route('admin.input.curah-hujan'))->assertSee(route('admin.input.debit'));
});

it('nilai negatif, eksponen, teks, dan terlalu besar ditolak dan dilaporkan, sisanya tetap disimpan', function () {
    $this->actingAs(akun('superadmin'));
    $tempel = "1	-5	1e7	2,5
2	abc	1000000	3
";

    $this->post('/admin/input/curah-hujan/pratinjau', ['pos_id' => 20, 'tahun' => 2025, 'tempel' => $tempel])->assertOk()
        ->assertSee('2 nilai akan disimpan')
        ->assertSee('Ditolak karena bukan angka positif atau terlalu besar')
        ->assertSee('1/1 (&quot;-5&quot;)', false)
        ->assertSee('1/2 (&quot;1e7&quot;)', false)
        ->assertSee('2/2 (&quot;1000000&quot;)', false);

    $this->post('/admin/input/curah-hujan', ['pos_id' => 20, 'tahun' => 2025, 'tempel' => $tempel])->assertRedirect();
    expect(PengamatanArr::orderBy('waktu')->pluck('hujan')->all())->toBe([2.5, 3.0]);
});

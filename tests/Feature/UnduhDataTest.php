<?php

use App\Enums\StatusPermintaan;
use App\Models\ArsipPos;
use App\Models\PermintaanData;
use App\Models\PermintaanDataPos;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 3, 'nama' => 'DINAS PUP ESDM DIY'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'das' => 'Progo', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual', 'data_tersedia_dari' => 2017, 'data_tersedia_sampai' => 2025]);
    Pos::forceCreate(['id' => 11, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'das' => 'Serang', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual', 'data_tersedia_dari' => 2020]);
    Pos::forceCreate(['id' => 30, 'walidata_id' => 3, 'nama' => 'Pos DIY', 'das' => 'Progo', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual', 'data_tersedia_dari' => 2019, 'data_tersedia_sampai' => 2024]);
    Pos::forceCreate(['id' => 40, 'walidata_id' => 1, 'nama' => 'Pos Tanpa Arsip', 'das' => 'Progo', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

function ajukan(array $pos): void
{
    test()->post('/admin/unduh-data', ['nama_pemohon' => 'Budi', 'instansi' => 'DPUP', 'jabatan' => 'Staf', 'tujuan' => 'Kajian banjir', 'pos' => $pos])
        ->assertRedirect('/admin/unduh-data');
}

it('form pengajuan mengelompokkan pos per DAS dengan rentang ketersediaan', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 3]))->get('/admin/unduh-data/tambah')
        ->assertOk()
        ->assertSeeInOrder(['Progo', 'Bendung Glapan', '[2017 – 2025]', 'Pos DIY', 'Serang', 'ARR Sermo', '[2020 – sekarang]'])
        ->assertDontSee('Pos Tanpa Arsip');
});

it('pengajuan dipecah per walidata dan hanya terlihat oleh pemohonnya', function () {
    $this->actingAs($pemohon = akun('admin_walidata', ['walidata_id' => 3]));
    ajukan([10, 11, 30]);

    $permintaan = PermintaanData::sole();
    expect($permintaan->pengguna_id)->toBe($pemohon->id)
        ->and($permintaan->item()->count())->toBe(3)
        ->and($permintaan->item()->whereNull('token_unduh')->count())->toBe(0);

    $this->get('/admin/unduh-data')
        ->assertOk()
        ->assertSeeInOrder(['Nama', 'Instansi', 'Jabatan', 'Tujuan', 'Walidata', 'Status', 'Keterangan', 'Waktu Pengajuan'])
        ->assertSee('BBWS SERAYU OPAK')
        ->assertSee('2 pos')
        ->assertSee('DINAS PUP ESDM DIY')
        ->assertSee('Pengajuan')
        ->assertSee('bg-green-50', false);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 11, 'username' => 'lain']))->get('/admin/unduh-data')
        ->assertOk()->assertDontSee('Budi');
});

it('pengajuan wajib nama dan minimal satu pos yang punya ketersediaan', function () {
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/unduh-data', ['nama_pemohon' => ''])->assertSessionHasErrors(['nama_pemohon', 'pos']);
    $this->post('/admin/unduh-data', ['nama_pemohon' => 'Budi', 'pos' => [40]])->assertSessionHasErrors('pos.0');
    expect(PermintaanData::count())->toBe(0);
});

it('walidata pemilik pos memverifikasi semua posnya sekaligus, walidata lain tidak bisa', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 3]));
    ajukan([10, 11, 30]);
    $permintaan = PermintaanData::sole();

    $this->actingAs($bbws = akun('admin_walidata', ['walidata_id' => 1, 'username' => 'bbws']));
    $this->get('/admin/unduh-data/verifikasi')->assertOk()->assertSee('Budi')->assertSee('BBWS SERAYU OPAK')->assertDontSee('DINAS PUP ESDM DIY');

    $this->post("/admin/unduh-data/{$permintaan->id}/1/proses", ['status' => 'disetujui'])->assertRedirect('/admin/unduh-data/verifikasi');
    expect($permintaan->item()->whereIn('pos_id', [10, 11])->pluck('status')->unique()->all())->toEqual([StatusPermintaan::Disetujui])
        ->and($permintaan->item()->where('pos_id', 30)->value('status'))->toBe(StatusPermintaan::Pengajuan)
        ->and($permintaan->item()->where('pos_id', 10)->value('diproses_oleh'))->toBe($bbws->id);

    // Walidata BBWS tidak bisa memproses bagian DPUP.
    $this->post("/admin/unduh-data/{$permintaan->id}/3/proses", ['status' => 'disetujui'])->assertNotFound();
});

it('detail dan unduh hanya untuk yang disetujui; unduhan dihitung dan file sesuai tahun', function () {
    Storage::fake('public');
    Storage::disk('public')->put('file/arsip/10/2024.pdf', 'isi 2024');
    Storage::disk('public')->put('file/arsip/10/2025.pdf', 'isi 2025');
    $a2024 = ArsipPos::create(['pos_id' => 10, 'tahun' => 2024, 'path' => 'file/arsip/10/2024.pdf']);
    $a2025 = ArsipPos::create(['pos_id' => 10, 'tahun' => 2025, 'path' => 'file/arsip/10/2025.pdf']);
    $arsipDiy = ArsipPos::create(['pos_id' => 30, 'tahun' => 2024, 'path' => 'file/arsip/30/2024.pdf']);

    $this->actingAs($pemohon = akun('admin_walidata', ['walidata_id' => 3]));
    ajukan([10]);
    $permintaan = PermintaanData::sole();
    $item = PermintaanDataPos::sole();

    $this->get("/admin/unduh-data/{$permintaan->id}/1")->assertOk()->assertSee('belum disetujui');
    $this->get("/admin/unduh-data/file/{$item->token_unduh}/{$a2024->id}")->assertNotFound();

    $item->update(['status' => StatusPermintaan::Disetujui]);
    $this->get('/admin/unduh-data')->assertSee(route('admin.unduh.detail', [$permintaan, 1]));
    $this->get("/admin/unduh-data/{$permintaan->id}/1")->assertOk()
        ->assertSeeInOrder(['Pos', '2025', '2024', 'Bendung Glapan'])
        ->assertSee(route('admin.unduh.file', [$item->token_unduh, $a2025]));

    $this->get("/admin/unduh-data/file/{$item->token_unduh}/{$a2024->id}")->assertOk()->assertStreamedContent('isi 2024');
    $this->get("/admin/unduh-data/file/{$item->token_unduh}/{$a2025->id}")->assertOk()->assertStreamedContent('isi 2025');
    expect($item->fresh()->jumlah_unduh)->toBe(2);

    // Arsip pos lain tidak bisa diunduh dengan token ini; pengguna lain tidak bisa memakai token.
    $this->get("/admin/unduh-data/file/{$item->token_unduh}/{$arsipDiy->id}")->assertNotFound();
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 11, 'username' => 'lain']))
        ->get("/admin/unduh-data/file/{$item->token_unduh}/{$a2024->id}")->assertNotFound();
});

it('pemohon menghapus pengajuannya per walidata', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 3]));
    ajukan([10, 30]);
    $permintaan = PermintaanData::sole();

    $this->delete("/admin/unduh-data/{$permintaan->id}/1")->assertRedirect();
    expect($permintaan->item()->pluck('pos_id')->all())->toBe([30]);

    $this->delete("/admin/unduh-data/{$permintaan->id}/3")->assertRedirect();
    expect(PermintaanData::count())->toBe(0);
});

it('menu Pengajuan dan Verifikasi di sidebar menjadi tautan', function () {
    $this->actingAs(akun('superadmin'))->get('/admin')
        ->assertSee(route('admin.unduh.pengajuan'))
        ->assertSee(route('admin.unduh.verifikasi'));
});

it('petugas pos tidak bisa membuka grup Ketersediaan data lewat URL (sama dengan sidebar)', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 3]));
    ajukan([10]);
    $permintaan = PermintaanData::sole();
    $arsip = ArsipPos::create(['pos_id' => 10, 'tahun' => 2025, 'path' => 'file/arsip/10/a.pdf']);

    $this->actingAs(akun('petugas_pos', ['pos_id' => 10, 'walidata_id' => 1, 'username' => 'petugas']));
    $this->get('/admin/unduh-data')->assertForbidden();
    $this->get('/admin/unduh-data/verifikasi')->assertForbidden();
    $this->post("/admin/unduh-data/{$permintaan->id}/1/proses", ['status' => 'disetujui'])->assertForbidden();
    $this->get('/admin/file-arsip')->assertForbidden();
    $this->post('/admin/file-arsip/10/2025/generate')->assertForbidden();
    $this->delete("/admin/file-arsip/berkas/{$arsip->id}")->assertForbidden();
    $this->get('/admin/ketersediaan/duga-air')->assertForbidden();

    expect($permintaan->item()->value('status'))->toBe(StatusPermintaan::Pengajuan)
        ->and(ArsipPos::count())->toBe(1);
});

it('pengajuan tetap terbuka walau ada item untuk pos yang sudah dihapus', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 3]));
    ajukan([10, 11]);
    Pos::find(11)->delete(); // hapus lunak

    $this->actingAs(akun('superadmin', ['username' => 'su']))->get('/admin/unduh-data')->assertOk()->assertSee('Budi')->assertSee('1 pos');
    $this->get('/admin/unduh-data/verifikasi')->assertOk();
});

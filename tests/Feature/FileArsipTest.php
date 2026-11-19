<?php

use App\Models\ArsipPos;
use App\Models\PengamatanArr;
use App\Models\PengamatanAwlr;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual', 'das' => 'Serang']);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 50, 'walidata_id' => 1, 'nama' => 'KA Progo', 'tipe' => 'hidrologi', 'jenis' => 'kualitas_air', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 60, 'walidata_id' => 11, 'nama' => 'Pos PJT', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

it('grid pos x tahun menandai tahun yang sudah punya arsip, dibatasi walidata', function () {
    ArsipPos::create(['pos_id' => 20, 'tahun' => now()->year - 1, 'path' => 'file/arsip/20/a.pdf']);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/file-arsip')
        ->assertOk()
        ->assertSeeInOrder(['Walidata / POS', (string) now()->year, (string) (now()->year - 10)])
        ->assertSeeInOrder(['ARR Sermo', 'Hidrometeorologi - ARR', 'Bendung Glapan', 'KA Progo'])
        ->assertSee('ARR Sermo '.(now()->year - 1).': arsip ada, kelola')
        ->assertSee('ARR Sermo '.now()->year.': belum ada arsip')
        ->assertDontSee('Pos PJT');
});

it('menyaring menurut jenis dan nama pos', function () {
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/file-arsip?jenis=awlr')->assertOk()->assertSee('Bendung Glapan')->assertSee('Pos PJT')->assertDontSee('ARR Sermo');
    $this->get('/admin/file-arsip?cari=progo')->assertOk()->assertSee('KA Progo')->assertDontSee('Bendung Glapan');
});

it('mengunggah PDF, mengganti file lama, dan menghapus arsip', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->post('/admin/file-arsip/50/2024/unggah', ['file' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4 arsip lama')])
        ->assertRedirect('/admin/file-arsip/50/2024');
    $lama = ArsipPos::sole();
    expect($lama->path)->toStartWith('file/arsip/50/2024-50-ka-progo-')
        ->and($lama->ukuran)->toBeGreaterThan(0);
    Storage::disk('public')->assertExists($lama->path);

    $this->travel(2)->seconds();
    $this->post('/admin/file-arsip/50/2024/unggah', ['file' => UploadedFile::fake()->createWithContent('b.pdf', '%PDF-1.4 arsip baru')]);
    $baru = ArsipPos::sole();
    expect($baru->path)->not->toBe($lama->path);
    Storage::disk('public')->assertMissing($lama->path);

    $this->post('/admin/file-arsip/50/2024/unggah', ['file' => UploadedFile::fake()->create('c.docx', 10)])->assertSessionHasErrors('file');

    $this->get("/admin/file-arsip/berkas/{$baru->id}")->assertOk();
    $this->delete("/admin/file-arsip/berkas/{$baru->id}")->assertRedirect('/admin/file-arsip/50/2024');
    expect(ArsipPos::count())->toBe(0);
    Storage::disk('public')->assertMissing($baru->path);
});

it('generate PDF buku data hujan harian dari pengamatan manual ARR', function () {
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2025-01-05 07:00:00', 'hujan' => 12.5]);
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2025-02-10 07:00:00', 'hujan' => 0]);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/file-arsip/20/2025')->assertOk()->assertSee('Generate PDF');
    $this->post('/admin/file-arsip/20/2025/generate')->assertRedirect('/admin/file-arsip/20/2025');

    $arsip = ArsipPos::sole();
    expect($arsip->path)->toStartWith('file/arsip/20/2025-20-arr-sermo-');
    expect(Storage::disk('public')->get($arsip->path))->toStartWith('%PDF');
});

it('isi buku data: nilai harian, x untuk kosong, ringkasan hujan', function () {
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2025-01-05 07:00:00', 'hujan' => 12.5]);
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2025-01-20 07:00:00', 'hujan' => 3]);
    PengamatanArr::create(['pos_id' => 20, 'waktu' => '2025-01-21 07:00:00', 'hujan' => 0]);

    $html = view('admin.file-arsip.pdf', [
        'pos' => Pos::with('walidata')->find(20), 'tahun' => 2025, 'hujan' => true,
        'nilai' => [1 => [5 => 12.5, 20 => 3.0, 21 => 0.0]],
        'hariPerBulan' => array_combine(range(1, 12), [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]),
    ])->render();

    expect($html)->toContain('DATA HUJAN HARIAN TAHUN 2025')
        ->toContain('ARR Sermo')
        ->toContain('<td>12.5</td>')
        ->toContain('<td>x</td>')
        ->toContain('class="kosong"') // 30 Februari
        ->toContain('<td colspan="4">15.5</td>') // jumlah curah hujan tahunan
        ->toContain('<td colspan="4">2</td>') // hari hujan: hanya hujan > 0
        ->toContain('12.5 (5 Januari 2025)'); // hujan maksimum dan tanggalnya
});

it('generate tidak tersedia untuk pos selain ARR/AWLR; pos walidata lain tidak bisa dikelola', function () {
    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]));

    $this->get('/admin/file-arsip/50/2025')->assertOk()->assertDontSee('Generate PDF');
    $this->post('/admin/file-arsip/50/2025/generate')->assertNotFound();
    $this->get('/admin/file-arsip/60/2025')->assertNotFound();
    $this->post('/admin/file-arsip/60/2025/unggah', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertNotFound();
});

it('generate AWLR memakai rata-rata limpas harian; menu menjadi tautan', function () {
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => '2025-03-01 07:00:00', 'limpas' => 1.2]);
    PengamatanAwlr::create(['pos_id' => 10, 'waktu' => '2025-03-01 12:00:00', 'limpas' => 1.6]);
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/file-arsip/10/2025/generate')->assertRedirect();
    expect(Storage::disk('public')->get(ArsipPos::sole()->path))->toStartWith('%PDF');
    $this->get('/admin')->assertSee(route('admin.arsip'));
});

it('arsip dobel per pos/tahun dirapikan menjadi satu saat diunggah; tahun tidak wajar 404', function () {
    ArsipPos::create(['pos_id' => 50, 'tahun' => 2020, 'path' => 'file/arsip/50/lama1.pdf']);
    ArsipPos::create(['pos_id' => 50, 'tahun' => 2020, 'path' => 'file/arsip/50/lama2.pdf']);
    Storage::disk('public')->put('file/arsip/50/lama2.pdf', 'lama');
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/file-arsip/50/2020/unggah', ['file' => UploadedFile::fake()->createWithContent('baru.pdf', '%PDF-1.4 baru')])->assertRedirect();

    $arsip = ArsipPos::where(['pos_id' => 50, 'tahun' => 2020])->get();
    expect($arsip)->toHaveCount(1)->and($arsip->first()->path)->toStartWith('file/arsip/50/2020-50-');
    Storage::disk('public')->assertMissing('file/arsip/50/lama2.pdf');

    $this->get('/admin/file-arsip/50/99999')->assertNotFound();
    $this->post('/admin/file-arsip/50/1800/unggah', ['file' => UploadedFile::fake()->createWithContent('x.pdf', '%PDF')])->assertNotFound();
});

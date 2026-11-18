<?php

use App\Models\Buletin;
use App\Models\Lampiran;
use App\Models\Peta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.telemetri.zona_waktu' => 'Asia/Jakarta']);
    Storage::fake('public');
    $this->travelTo(Carbon::parse('2026-11-19 00:15', 'Asia/Jakarta'));

    Http::fake([
        'staklim-yogya.bmkg.go.id/feed/' => Http::response(<<<'XML'
            <?xml version="1.0"?>
            <rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel><item>
              <title>Analisis Curah Hujan Harian 18 November 2026</title>
              <link>https://staklim-yogya.bmkg.go.id/2026/11/18/analisis/</link>
              <pubDate>Wed, 18 Nov 2026 02:17:41 +0000</pubDate>
              <category><![CDATA[Analisis Curah Hujan Harian]]></category>
              <content:encoded><![CDATA[<img src="https://staklim-yogya.bmkg.go.id/wp-content/CH-18.png" /><p>Hujan ringan &amp; sedang</p>]]></content:encoded>
            </item></channel></rss>
            XML),
        'staklim-jateng.bmkg.go.id/monitoring_curah_hujan_harian.php' => Http::response(
            '<div class="content-section"><div class="card monitor-card"><h5>Curah Hujan Harian</h5><img src="upload/harian/abc.png" alt="">'
            .'<div>Deskripsi: monitoring curah hujan 18 November 2026</div><div>Ringkasan Kondisi: Hujan &gt; 20 mm</div>'
            .'<div>Diupdate: 18/11/2026 07:30</div></div></div><div class="modal"></div>'
        ),
        'staklim-jateng.bmkg.go.id/info-dasarian.php' => Http::response('<header></header><hr>'),
        'staklim-jateng.bmkg.go.id/info-bulanan.php' => Http::response(
            '<header></header><div class="card-header"><h6><i></i> Prediksi Curah Hujan 3 Bulan Kedepan</h6></div><img src="upload/bulanan/x.webp" alt="">'
            .'<div>Deskripsi: Prediksi Curah Hujan bulan Februari 2027 berkisar 101 - 500 mm</div><hr>'
        ),
        '*' => Http::response('isi-gambar'),
    ]);
});

it('mengambil buletin Yogya & Jateng sebagai draf beserta gambarnya', function () {
    $this->artisan('konten:buletin-bmkg')
        ->expectsOutputToContain('Staklim Yogya: 1 buletin baru')
        ->expectsOutputToContain('Staklim Jateng: 2 buletin baru')
        ->assertSuccessful();

    $yogya = Buletin::where('sumber', 'like', '%Yogyakarta')->sole();
    expect([$yogya->terbit, $yogya->ringkasan, $yogya->tanggal->toDateTimeString(), $yogya->kategori->nama])
        ->toBe([false, 'Hujan ringan & sedang', '2026-11-18 09:17:41', 'Analisis Curah Hujan Harian']);
    Storage::disk('public')->assertExists('file/buletin/staklim-yogyakarta/2026/11-18-ch-18.png');

    $harian = Buletin::where('url_sumber', 'like', '%harian%')->sole();
    expect([$harian->judul, $harian->ringkasan, $harian->tanggal->toDateTimeString()])
        ->toBe(['Monitoring curah hujan 18 November 2026', 'Hujan > 20 mm', '2026-11-18 07:30:00']);

    $bulanan = Buletin::where('url_sumber', 'like', '%bulanan%')->sole();
    expect([$bulanan->judul, $bulanan->kategori->nama])->toBe(['Prediksi Curah Hujan bulan Februari 2027', 'Prediksi Curah Hujan 3 Bulan Kedepan']);
});

it('tidak menyimpan ulang buletin yang sudah ada', function () {
    $this->artisan('konten:buletin-bmkg');
    $this->artisan('konten:buletin-bmkg')
        ->expectsOutputToContain('Staklim Yogya: 0 buletin baru')
        ->expectsOutputToContain('Staklim Jateng: 0 buletin baru');

    expect(Buletin::count())->toBe(3);
});

it('menghapus duplikat buletin Jateng hasil impor, menyisakan yang terbit', function () {
    $buat = fn (string $sumber, string $gambar, bool $terbit = false) => tap(Buletin::create([
        'tipe' => 'otomatis', 'judul' => 'Analisis Agustus 2026', 'sumber' => 'BMKG', 'url_sumber' => $sumber,
        'url_gambar_sumber' => $gambar, 'terbit' => $terbit, 'tanggal' => now(),
    ]), fn ($b) => $b->lampiran()->create(['jenis' => 'gambar', 'path' => "file/{$b->id}.webp"]));

    $jateng = 'https://staklim-jateng.bmkg.go.id/info-bulanan.php';
    $salinan1 = $buat($jateng, 'https://staklim-jateng.bmkg.go.id/upload/a.webp');
    $terbit = $buat($jateng, 'https://staklim-jateng.bmkg.go.id/upload/a.webp', terbit: true);
    $salinan2 = $buat($jateng, 'https://staklim-jateng.bmkg.go.id/upload/a.webp');
    $lain = $buat($jateng, 'https://staklim-jateng.bmkg.go.id/upload/b.webp');
    // Yogya: gambar sama dipakai beberapa postingan berbeda, bukan duplikat.
    $yogya1 = $buat('https://staklim-yogya.bmkg.go.id/2026/11/01/a/', 'https://staklim-yogya.bmkg.go.id/logo.png');
    $yogya2 = $buat('https://staklim-yogya.bmkg.go.id/2026/11/02/b/', 'https://staklim-yogya.bmkg.go.id/logo.png');
    $peta = Peta::create(['nama' => 'Peta CH', 'buletin_id' => $salinan2->id]);

    expect(Buletin::hapusDuplikatOtomatis())->toBe(2);

    expect(Buletin::pluck('id')->sort()->values()->all())->toBe(collect([$terbit->id, $lain->id, $yogya1->id, $yogya2->id])->sort()->values()->all())
        ->and($peta->fresh()->buletin_id)->toBe($terbit->id)
        ->and(Lampiran::count())->toBe(4);
    expect($salinan1->id)->not->toBeIn(Buletin::pluck('id')->all());
});

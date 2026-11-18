<?php

namespace App\Services\Konten;

use App\Models\Buletin;
use App\Models\Kategori;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Buletin otomatis dari Stasiun Klimatologi BMKG (dulu Scrap.php). Disimpan sebagai draf (terbit = false).
 * - Yogyakarta: RSS https://staklim-yogya.bmkg.go.id/feed/
 * - Jawa Tengah: 3 halaman HTML, diurai dengan penanda teks yang sama dengan kode lama.
 * Gambar disimpan di disk `public` dengan path seperti lampiran hasil impor (file/buletin/...).
 */
class FetchDataBuletinBmkg
{
    private const JATENG = 'https://staklim-jateng.bmkg.go.id/';

    /** Jumlah buletin baru dari RSS Staklim Yogyakarta. */
    public function fetchYogya(): int
    {
        $xml = simplexml_load_string($this->unduh('https://staklim-yogya.bmkg.go.id/feed/'), options: LIBXML_NOCDATA)
            ?: throw new RuntimeException('Feed Staklim Yogya bukan RSS yang valid');

        $baru = 0;
        foreach ($xml->channel->item as $item) {
            $tautan = trim((string) $item->link);
            if (Buletin::where('url_sumber', $tautan)->exists()) {
                continue;
            }

            $isi = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
            $ringkasan = $this->teks($this->potong($isi, '<p>', '</p>')) ?? $this->teks((string) $item->description);
            preg_match('/<img[^>]+src="([^"]+)"/i', $isi, $gambar);

            $this->simpan([
                'judul' => $this->teks((string) $item->title),
                'kategori' => $this->teks((string) $item->category),
                'ringkasan' => $ringkasan,
                'isi' => $ringkasan,
                'sumber' => 'Stasiun Klimatologi DI Yogyakarta',
                'url_sumber' => $tautan,
                'url_gambar_sumber' => $gambar[1] ?? null,
                'tanggal' => Carbon::parse((string) $item->pubDate)->setTimezone(config('services.telemetri.zona_waktu')),
            ], 'staklim-yogyakarta');
            $baru++;
        }

        return $baru;
    }

    /** Jumlah buletin baru dari halaman harian, dasarian, dan bulanan Staklim Jawa Tengah. */
    public function fetchJateng(): int
    {
        $baru = 0;
        foreach (['monitoring_curah_hujan_harian.php', 'info-dasarian.php', 'info-bulanan.php'] as $halaman) {
            $html = $this->unduh(self::JATENG.$halaman);
            $harian = $halaman === 'monitoring_curah_hujan_harian.php';
            $kartu = $harian
                ? explode('class="card monitor-card', $this->potong($html, 'content-section', 'class="modal'))
                : explode('class="card-header', $this->potong($html, '</header>', '<hr'));
            array_shift($kartu);

            foreach ($kartu as $k) {
                $data = $harian ? $this->kartuHarian($k) : $this->kartuInfo($k);
                // Cek duplikat lewat URL gambar BMKG (kode lama membandingkan dengan path lokal, jadi selalu dobel).
                if (! $data['url_gambar_sumber'] || Buletin::where('url_gambar_sumber', $data['url_gambar_sumber'])->exists()) {
                    continue;
                }

                $this->simpan($data + ['sumber' => 'Stasiun Klimatologi Jawa Tengah', 'url_sumber' => self::JATENG.$halaman], 'staklim-jateng');
                $baru++;
            }
        }

        return $baru;
    }

    private function kartuHarian(string $k): array
    {
        $diupdate = $this->teks($this->potong($k, 'Diupdate:', '</div>')); // "16/10/2026 02:26"
        preg_match('/<h5[^>]*>(.*?)<\/h5/is', $k, $kategori);

        return [
            'judul' => Str::ucfirst(ltrim($this->teks($this->potong($k, 'Deskripsi', '</div>')) ?? '', ': ')),
            'kategori' => $this->teks($kategori[1] ?? null),
            'ringkasan' => ltrim($this->teks($this->potong($k, 'Ringkasan Kondisi', '</div>')) ?? '', ': ') ?: null,
            'url_gambar_sumber' => $this->gambar($k),
            'tanggal' => rescue(fn () => Carbon::createFromFormat('d/m/Y H:i', $diupdate, config('services.telemetri.zona_waktu')), now(), false),
        ];
    }

    /** Kartu dasarian/bulanan: judul kartu jadi kategori, judul buletin = deskripsi sampai tahun pertama. */
    private function kartuInfo(string $k): array
    {
        $kategori = $this->teks($this->potong($k, '</i>', '</h6>'));
        $ringkasan = $this->teks($this->potong($k, 'Deskripsi:', '</div>'));

        return [
            'judul' => preg_match('/^(.*?\b20\d{2})\b/s', (string) $ringkasan, $m) ? $m[1] : $kategori,
            'kategori' => $kategori,
            'ringkasan' => $ringkasan,
            'url_gambar_sumber' => $this->gambar($k),
            'tanggal' => now(),
        ];
    }

    private function simpan(array $data, string $folder): void
    {
        $kategori = $data['kategori']
            ? Kategori::firstOrCreate(['jenis' => 'buletin', 'slug' => Str::slug($data['kategori'])], ['nama' => $data['kategori']])
            : null;

        $buletin = Buletin::create([
            'kategori_id' => $kategori?->id,
            'tipe' => 'otomatis',
            'judul' => Str::limit($data['judul'] ?? '-', 250, ''),
            'ringkasan' => $data['ringkasan'],
            'isi' => $data['isi'] ?? null,
            'sumber' => $data['sumber'],
            'url_sumber' => $data['url_sumber'],
            'url_gambar_sumber' => $data['url_gambar_sumber'],
            'terbit' => false,
            'tanggal' => $data['tanggal'],
        ]);

        // Gambar gagal diunduh tidak membatalkan buletin, sama seperti kode lama.
        $gambar = $data['url_gambar_sumber'] ? rescue(fn () => $this->unduh($data['url_gambar_sumber']), null, false) : null;
        if ($gambar) {
            $nama = strtolower(basename(parse_url($data['url_gambar_sumber'], PHP_URL_PATH)));
            $path = "file/buletin/{$folder}/{$data['tanggal']->format('Y/m-d')}-{$nama}";
            Storage::disk('public')->put($path, $gambar);

            $buletin->lampiran()->create([
                'jenis' => 'gambar',
                'path' => $path,
                'ekstensi' => pathinfo($nama, PATHINFO_EXTENSION) ?: null,
                'ukuran' => strlen($gambar),
                'urutan' => 1,
            ]);
        }
    }

    private function unduh(string $url): string
    {
        $respons = Http::timeout(60)->withUserAgent('Mozilla/5.0 (compatible; SIH3-BBWS-Serayu-Opak)')->get($url);

        return $respons->successful() ? $respons->body() : throw new RuntimeException("Gagal mengunduh {$url}: HTTP {$respons->status()}");
    }

    private function gambar(string $k): ?string
    {
        if (! preg_match('/src="([^"]+)"/i', $k, $m)) {
            return null;
        }

        return Str::startsWith($m[1], 'http') ? $m[1] : self::JATENG.ltrim($m[1], '/');
    }

    /** Sama dengan SchCut() lama: teks di antara $awal dan $akhir yang pertama. */
    private function potong(string $teks, string $awal, string $akhir): string
    {
        if (! str_contains($teks, $awal)) {
            return '';
        }

        return Str::before(Str::after($teks, $awal), $akhir);
    }

    /** Buang tag & entitas HTML, rapikan spasi. Kosong -> null. */
    private function teks(?string $html): ?string
    {
        $teks = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5)));

        return $teks === '' ? null : $teks;
    }
}

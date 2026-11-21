<?php

namespace App\Http\Controllers;

use App\Enums\JenisPos;
use App\Enums\TipePos;
use App\Models\Galeri;
use App\Models\Pos;
use App\Models\Walidata;
use App\Services\KondisiTerkini;
use App\Services\NilaiTerakhirPos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Portal publik (pengganti Portal.php lama). Tahap 1: beranda.
 *
 * Isi mengikuti beranda lama (sih3.bbwsserayuopak.id): peta sebaran pos dengan filter jenis/walidata dan mode
 * "pos peringatan" (kelas hujan, status duga air, kelas kualitas air), tiga bidang pemantauan dengan rincian
 * per walidata, lalu walidata yang berkolaborasi.
 */
class PortalController extends Controller
{
    /** Kelompok penanda peta (= filter jenis di beranda lama) => [label, tipe]. AGWLR digabung ke muka air tanah. */
    public const KELOMPOK = [
        'awlr' => ['Duga air', 'hidrologi'],
        'kualitas_air' => ['Kualitas air', 'hidrologi'],
        'arr' => ['Curah hujan', 'hidrometeorologi'],
        'klimatologi' => ['Klimatologi', 'hidrometeorologi'],
        'mat' => ['Muka air tanah', 'hidrogeologi'],
    ];

    /** Kelas peringatan, sama dengan legenda peta lama. Indeks = kelas (1 = normal). */
    public const KELAS = [
        'arr' => [1 => 'Tidak hujan', 'Hujan sangat ringan', 'Hujan ringan', 'Hujan sedang', 'Hujan lebat', 'Hujan sangat lebat'],
        'awlr' => [1 => 'Normal', 'Waspada', 'Siaga', 'Awas'],
        'kualitas_air' => [1 => 'Baik', 'Cemar ringan', 'Cemar sedang', 'Cemar berat'],
    ];

    /** Definisi bidang, teks dari beranda lama. */
    public const DEFINISI = [
        'hidrologi' => 'Hidrologi adalah ilmu yang mempelajari tentang air yang terdapat pada dan/atau mengalir di permukaan tanah, termasuk pergerakan, distribusi, kualitas, serta interaksinya dengan air hujan dan air tanah.',
        'hidrometeorologi' => 'Hidrometeorologi adalah cabang ilmu yang mempelajari interaksi antara atmosfer (meteorologi) dan siklus air (hidrologi), khususnya dalam kaitannya dengan cuaca dan iklim, serta dampaknya terhadap bencana alam.',
        'hidrogeologi' => 'Hidrogeologi adalah cabang ilmu geologi yang mempelajari tentang air tanah, termasuk keberadaan, pergerakan, kualitas, dan interaksinya dengan batuan dan tanah di bawah permukaan bumi.',
    ];

    public function beranda(KondisiTerkini $kondisi, NilaiTerakhirPos $nilaiTerakhir): View
    {
        [$pos, $data] = $this->dataPeta($kondisi, $nilaiTerakhir);

        return view('portal.beranda', $data + ['bidang' => $this->bidang($pos)]);
    }

    /** Peta pos pemantauan layar penuh (pengganti info/pos_pemantauan lama): peta + panel filter. */
    public function posPemantauan(KondisiTerkini $kondisi, NilaiTerakhirPos $nilaiTerakhir): View
    {
        [, $data] = $this->dataPeta($kondisi, $nilaiTerakhir);

        return view('portal.pos-pemantauan', $data);
    }

    /**
     * Data peta bersama beranda dan halaman Pos Pemantauan: titik pos aktif berkoordinat, jumlah per kelompok, walidata.
     *
     * @return array{0: Collection<int, Pos>, 1: array<string, mixed>}
     */
    private function dataPeta(KondisiTerkini $kondisi, NilaiTerakhirPos $nilaiTerakhir): array
    {
        $pos = Pos::with('walidata:id,nama', 'perangkatTelemetri', 'batasSiaga')->where('aktif', true)->orderBy('nama')->get();
        $nilai = $nilaiTerakhir->untuk($pos);
        $hujan24 = $kondisi->hujan24Jam($pos->modelKeys());
        $titik = $pos->filter(fn (Pos $p) => $p->latitude && $p->longitude)
            ->map(fn (Pos $p) => $this->titik($p, $nilai[$p->id], $hujan24[$p->id] ?? 0.0))->values();

        return [$pos, [
            'titik' => $titik,
            'tanpaKoordinat' => $pos->count() - $titik->count(),
            'jumlahPerKelompok' => $titik->countBy('kel'),
            'jumlahPeringatan' => $titik->whereNotNull('kelas')->count(),
            'walidata' => Walidata::withCount(['pos' => fn ($q) => $q->where('aktif', true)])->orderBy('urutan')->get(),
        ]];
    }

    /**
     * Isi jendela detail saat pos di peta diklik (pengganti portal/detilpos lama): nilai terkini + status, data pos,
     * lokasi, walidata beserta kontaknya, dan foto pos terbaru dari galeri. Dimuat peta.js lewat fetch.
     */
    public function ringkasPos(Pos $pos, KondisiTerkini $kondisi, NilaiTerakhirPos $nilaiTerakhir): View
    {
        abort_unless($pos->aktif, 404);
        $pos->load('walidata', 'perangkatTelemetri', 'batasSiaga');
        $n = $nilaiTerakhir->untuk(collect([$pos]))[$pos->id];
        $titik = $this->titik($pos, $n, $kondisi->hujan24Jam([$pos->id])[$pos->id] ?? 0.0);

        // Foto terbaru yang terbit dan berkasnya memang ada di disk public (storage:link).
        $foto = Galeri::with('lampiran')->where('pos_id', $pos->id)->where('terbit', true)->latest('tanggal')->get()
            ->flatMap->lampiran->first(fn ($l) => $l->jenis === 'gambar' && Storage::disk('public')->exists($l->path));

        return view('portal.pos-ringkas', [
            'pos' => $pos,
            't' => $titik,
            'foto' => $foto ? Storage::disk('public')->url($foto->path) : null,
        ]);
    }

    /**
     * Ringkasan per bidang (tipe pos): jumlah pos, instansi, dan rincian per walidata per jenis.
     *
     * @return Collection<string, array{label: string, definisi: string, pos: int, instansi: int, rincian: Collection}>
     */
    private function bidang(Collection $pos): Collection
    {
        return collect(TipePos::cases())->mapWithKeys(function (TipePos $tipe) use ($pos) {
            $diBidang = $pos->where('tipe', $tipe);
            $rincian = $diBidang->groupBy('walidata_id')->map(fn ($kelompok) => [
                'nama' => $kelompok->first()->walidata?->nama ?? 'Tanpa walidata',
                'jumlah' => $kelompok->count(),
                'jenis' => $kelompok->countBy(fn (Pos $p) => self::KELOMPOK[$this->kelompok($p)][0])->sortDesc(),
            ])->sortByDesc('jumlah')->values();

            return [$tipe->value => [
                'label' => $tipe->label(),
                'definisi' => self::DEFINISI[$tipe->value],
                'pos' => $diBidang->count(),
                'instansi' => $rincian->count(),
                'rincian' => $rincian,
            ]];
        });
    }

    private function kelompok(Pos $p): string
    {
        return $p->jenis === JenisPos::Agwlr ? 'mat' : $p->jenis->value;
    }

    /**
     * Kelas peringatan pos (null = tidak punya status terkini), mengikuti mode "pos peringatan" beranda lama:
     * curah hujan dari total 24 jam telemetri, duga air dari batas siaga, kualitas air dari indeks pencemaran (PIj).
     */
    private function kelas(Pos $p, array $n, float $hujan24): ?int
    {
        $online = $n['status'] === 'online';

        return match ($this->kelompok($p)) {
            'arr' => $online ? match (true) {
                $hujan24 <= 0 => 1, $hujan24 <= 1 => 2, $hujan24 <= 5 => 3, $hujan24 <= 10 => 4, $hujan24 < 20 => 5, default => 6,
            } : null,
            'awlr' => $online && $p->batasSiaga->isNotEmpty() ? $n['siaga'] : null,
            'kualitas_air' => $n['nilai'] === null ? null : match (true) {
                $n['nilai'] <= 1 => 1, $n['nilai'] <= 5 => 2, $n['nilai'] <= 10 => 3, default => 4,
            },
            default => null,
        };
    }

    /**
     * Data satu titik peta (dikirim ke resources/js/peta.js sebagai JSON).
     *
     * @param  array{label: string, nilai: ?float, satuan: string, waktu: mixed, status: string, siaga: int}  $n
     * @return array<string, mixed>
     */
    private function titik(Pos $p, array $n, float $hujan24): array
    {
        $kel = $this->kelompok($p);
        $kelas = $this->kelas($p, $n, $hujan24);

        return [
            'id' => $p->id,
            'nama' => $p->nama,
            'lat' => (float) $p->latitude,
            'lng' => (float) $p->longitude,
            'kel' => $kel,
            'tipe' => $p->tipe->value,
            'jenis' => self::KELOMPOK[$kel][0],
            'wid' => $p->walidata_id,
            'walidata' => $p->walidata?->nama,
            'label' => $n['label'],
            'nilai' => $n['nilai'] === null ? null : $this->angka($n['nilai']).($n['satuan'] ? ' '.$n['satuan'] : ''),
            'waktu' => $n['waktu']?->translatedFormat('j M Y, H.i'),
            'status' => $n['status'],
            'hujan24' => $kel === 'arr' && $n['status'] === 'online' ? $this->angka($hujan24).' mm' : null,
            'kelas' => $kelas,
            'kelasLabel' => $kelas ? self::KELAS[$kel][$kelas] : null,
        ];
    }

    private function angka(float $n): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.'));
    }
}

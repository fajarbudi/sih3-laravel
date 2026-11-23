<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\MetodePos;
use App\Enums\PeranPengguna;
use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalController;
use App\Models\PengamatanArr;
use App\Models\PengamatanAwlr;
use App\Models\PermintaanDataPos;
use App\Models\Pos;
use App\Services\KondisiTerkini;
use App\Services\NilaiTerakhirPos;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dashboard kondisi lapangan untuk pos yang boleh dilihat pengguna:
 * - ringkasan: status siaga, hujan tertinggi, telemetri yang mengirim data, pengisian manual kemarin;
 * - hujan, muka air, cuaca (App\Services\KondisiTerkini, dipakai bersama beranda portal);
 * - pos di atas batas siaga, kesehatan telemetri per sumber, pengisian data manual kemarin
 *   (status online/siaga dari App\Services\NilaiTerakhirPos, sama dengan peta portal).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, KondisiTerkini $kondisi, NilaiTerakhirPos $nilaiTerakhir): View
    {
        $posId = Pos::terlihatOleh($request->user())->select('id');
        $pos = Pos::terlihatOleh($request->user())->where('aktif', true)->with('perangkatTelemetri', 'batasSiaga')->orderBy('nama')->get();
        $nilai = $nilaiTerakhir->untuk($pos);
        $mukaAir = $kondisi->mukaAir($posId);

        return view('admin.dashboard', [
            'sekarang' => now(config('services.telemetri.zona_waktu')),
            'ambangPerubahan' => KondisiTerkini::AMBANG_PERUBAHAN_M,
            'jumlahMenunggu' => PermintaanDataPos::whereIn('pos_id', $posId)->where('status', StatusPermintaan::Pengajuan)->count(),
            'hujanPerJam' => $kondisi->hujanPerJam($posId),
            'hujanPerPos' => $kondisi->hujanPerPos($posId),
            'mukaAir' => $mukaAir,
            'posNaik' => $mukaAir->filter(fn ($m) => $m['perubahan'] !== null && $m['perubahan'] >= KondisiTerkini::AMBANG_PERUBAHAN_M)->count(),
            'cuaca' => $kondisi->cuaca($posId),
            'siaga' => $this->siaga($pos, $nilai),
            'telemetri' => $this->telemetri($pos, $nilai),
            'kemarin' => today(config('services.telemetri.zona_waktu'))->subDay(),
            'manual' => $this->manual($pos, $request->user()->peran !== PeranPengguna::PetugasPos),
        ]);
    }

    /**
     * Pos duga air telemetri yang masih mengirim data dan melewati batas Waspada/Siaga/Awas, level tertinggi dulu.
     *
     * @return Collection<int, array{nama: string, level: int, label: string, nilai: float, waktu: mixed}>
     */
    private function siaga(Collection $pos, array $nilai): Collection
    {
        return $pos->filter(fn (Pos $p) => $nilai[$p->id]['status'] === 'online' && $nilai[$p->id]['siaga'] >= 2)
            ->map(fn (Pos $p) => [
                'nama' => $p->nama,
                'level' => $nilai[$p->id]['siaga'],
                'label' => PortalController::KELAS['awlr'][$nilai[$p->id]['siaga']],
                'nilai' => $nilai[$p->id]['nilai'],
                'waktu' => $nilai[$p->id]['waktu'],
            ])
            ->sortByDesc('level')->values();
    }

    /**
     * Pos telemetri per sumber: jumlah yang mengirim data dalam 24 jam dan yang tidak, plus daftar yang tidak mengirim.
     *
     * @return array{sumber: Collection, mati: Collection, online: int, total: int}
     */
    private function telemetri(Collection $pos, array $nilai): array
    {
        $telemetri = $pos->filter(fn (Pos $p) => $p->metode === MetodePos::Telemetri);
        $sumber = fn (Pos $p) => $p->perangkatTelemetri->first()?->sumber?->label() ?? 'Tanpa perangkat';

        return [
            'sumber' => $telemetri->groupBy($sumber)->map(fn ($g) => [
                'online' => $g->filter(fn (Pos $p) => $nilai[$p->id]['status'] === 'online')->count(),
                'total' => $g->count(),
            ])->sortByDesc('total'),
            'mati' => $telemetri->filter(fn (Pos $p) => $nilai[$p->id]['status'] !== 'online')
                ->map(fn (Pos $p) => ['nama' => $p->nama, 'sumber' => $sumber($p), 'waktu' => $nilai[$p->id]['waktu']])
                ->sortBy(fn ($m) => $m['waktu']?->timestamp ?? 0)->values(),
            'online' => $telemetri->filter(fn (Pos $p) => $nilai[$p->id]['status'] === 'online')->count(),
            'total' => $telemetri->count(),
        ];
    }

    /**
     * Pos manual yang diisi petugas kemarin, untuk duga air dan curah hujan. Tautan ke daftar hanya untuk admin
     * (petugas tidak punya menu monitoring).
     *
     * @return array<string, array{label: string, isi: int, total: int, rute: ?string}>
     */
    private function manual(Collection $pos, bool $denganTautan): array
    {
        // Hari dihitung dalam WIB (zona aplikasi UTC; pukul 00.00-07.00 WIB masih tanggal kemarin di UTC).
        $kemarin = today(config('services.telemetri.zona_waktu'))->subDay()->toDateString();
        $hasil = [];
        foreach ([[JenisPos::Awlr, PengamatanAwlr::class, 'Duga air', 'admin.monitoring-duga-air'], [JenisPos::Arr, PengamatanArr::class, 'Curah hujan', 'admin.curah-hujan']] as [$jenis, $model, $label, $rute]) {
            $id = $pos->filter(fn (Pos $p) => $p->jenis === $jenis && $p->metode === MetodePos::Manual)->modelKeys();
            $hasil[$jenis->value] = [
                'label' => $label,
                'isi' => $id ? $model::whereIn('pos_id', $id)->whereDate('waktu', $kemarin)->distinct()->count('pos_id') : 0,
                'total' => count($id),
                'rute' => $denganTautan ? $rute : null,
            ];
        }

        return array_filter($hasil, fn ($h) => $h['total'] > 0);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Http\Controllers\Controller;
use App\Models\BatasSiaga;
use App\Models\Pos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ambang batas siaga per pos (asal: Batas.php). Seperti aplikasi lama: satu grid berisi semua pos satu jenis
 * (default AWLR) dengan isian Batas 1 s.d. Batas 6, disimpan sekaligus. Status siaga pos = level tertinggi
 * yang nilainya dilampaui (lihat Pos::levelSiaga).
 *
 * Perbedaan dengan aplikasi lama: simpan hanya mengganti batas pos yang tampil di grid (dulu tabel ref_batas
 * dikosongkan seluruhnya, sehingga batas jenis lain ikut terhapus), dan ada pencarian nama pos.
 */
class AmbangBatasController extends Controller
{
    public const LEVEL = [1, 2, 3, 4, 5, 6];

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'jenis' => ['nullable', Rule::enum(JenisPos::class)],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $jenis = JenisPos::from($filter['jenis'] ?? JenisPos::Awlr->value);

        $pos = Pos::with('batasSiaga')->jenis($jenis)
            ->when($filter['cari'] ?? null, fn ($q, $c) => $q->where('nama', 'like', '%'.$c.'%'))
            ->orderBy('nama')
            ->get(['id', 'nama', 'walidata_id']);

        return view('admin.grid-pos', [
            'judul' => 'Ambang Batas',
            'kolom' => collect(self::LEVEL)->mapWithKeys(fn ($l) => [$l => 'Batas '.$l])->all(),
            'nilai' => $pos->mapWithKeys(fn ($p) => [$p->id => $p->batasSiaga->pluck('nilai', 'level')->all()])->all(),
            'rute' => 'admin.batas-siaga',
            'keterangan' => 'Status siaga pos = level tertinggi yang nilainya dilampaui. Kosongkan isian untuk menghapus batas.',
            'pos' => $pos,
            'jenis' => $jenis,
            'cari' => $filter['cari'] ?? null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'batas' => ['required', 'array'],
            'batas.*' => ['array'],
            'batas.*.*' => ['nullable', 'numeric', 'between:-9999999,9999999'],
        ], ['batas.*.*.numeric' => 'Batas harus berupa angka.']);

        $posId = Pos::whereKey(array_keys($data['batas']))->pluck('id');
        DB::transaction(function () use ($data, $posId) {
            foreach ($posId as $id) {
                BatasSiaga::where('pos_id', $id)->delete();
                foreach (self::LEVEL as $level) {
                    $nilai = $data['batas'][$id][$level] ?? null;
                    if ($nilai !== null && $nilai !== '') {
                        BatasSiaga::create(['pos_id' => $id, 'level' => $level, 'nilai' => $nilai]);
                    }
                }
            }
        });

        return back()->with('pesan', 'Ambang batas '.$posId->count().' pos disimpan.');
    }
}

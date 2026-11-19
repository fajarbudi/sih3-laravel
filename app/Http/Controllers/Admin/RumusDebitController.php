<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Http\Controllers\Controller;
use App\Models\Pos;
use App\Models\RumusDebit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Koefisien rumus debit per pos (asal: Rumus_debit.php). Seperti aplikasi lama: grid semua pos satu jenis
 * (default AWLR) dengan isian R_1 s.d. R_6, disimpan sekaligus; koefisien disimpan sebagai array di rumus_debit.koefisien.
 *
 * Perbedaan dengan aplikasi lama: simpan hanya mengganti pos yang tampil di grid (dulu ref_rumus_debit dikosongkan
 * seluruhnya). Kolom rumus dan berlaku_mulai belum diisi dari layar ini (tidak ada di aplikasi lama).
 */
class RumusDebitController extends Controller
{
    public const KOEFISIEN = [1, 2, 3, 4, 5, 6];

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'jenis' => ['nullable', Rule::enum(JenisPos::class)],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $jenis = JenisPos::from($filter['jenis'] ?? JenisPos::Awlr->value);

        $pos = Pos::with('rumusDebit')->jenis($jenis)
            ->when($filter['cari'] ?? null, fn ($q, $c) => $q->where('nama', 'like', '%'.$c.'%'))
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return view('admin.grid-pos', [
            'judul' => 'Rumus Debit',
            'kolom' => collect(self::KOEFISIEN)->mapWithKeys(fn ($i) => [$i => 'R_'.$i])->all(),
            // koefisien disimpan berindeks 0; kolom grid berindeks 1 (R_1 = koefisien[0]).
            'nilai' => $pos->mapWithKeys(fn ($p) => [$p->id => collect($p->rumusDebit->first()?->koefisien ?? [])->mapWithKeys(fn ($v, $i) => [$i + 1 => $v])->all()])->all(),
            'rute' => 'admin.rumus-debit',
            'keterangan' => 'Koefisien rumus debit R_1 s.d. R_6 per pos. Kosongkan semua isian pos untuk menghapus rumusnya.',
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
            'batas.*.*' => ['nullable', 'numeric'],
        ], ['batas.*.*.numeric' => 'Koefisien harus berupa angka.']);

        $posId = Pos::whereKey(array_keys($data['batas']))->pluck('id');
        DB::transaction(function () use ($data, $posId) {
            foreach ($posId as $id) {
                $koefisien = collect(self::KOEFISIEN)->map(fn ($i) => isset($data['batas'][$id][$i]) && $data['batas'][$id][$i] !== '' ? (float) $data['batas'][$id][$i] : null);
                if ($koefisien->filter(fn ($v) => $v !== null)->isEmpty()) {
                    RumusDebit::where('pos_id', $id)->delete();
                } else {
                    // Ubah baris yang ditampilkan grid (berlaku_mulai terbaru, lihat Pos::rumusDebit); buat baru bila belum ada.
                    $rumus = Pos::find($id)->rumusDebit()->first() ?? new RumusDebit(['pos_id' => $id]);
                    $rumus->fill(['koefisien' => $koefisien->all()])->save();
                }
            }
        });

        return back()->with('pesan', 'Rumus debit '.$posId->count().' pos disimpan.');
    }
}

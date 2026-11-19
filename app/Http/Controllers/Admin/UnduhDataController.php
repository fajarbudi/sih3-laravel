<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Models\ArsipPos;
use App\Models\PermintaanData;
use App\Models\PermintaanDataPos;
use App\Models\Pos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Permintaan unduh data arsip pos (asal: Unduh.php). Seperti aplikasi lama:
 * - Pengajuan: pemohon (akun admin) melihat permintaannya sendiri; satu baris per permintaan x walidata.
 *   Pos dipilih per DAS dari pos yang punya rentang ketersediaan data.
 * - Verifikasi: admin walidata pemilik pos menyetujui atau menutup permintaan untuk semua posnya sekaligus.
 * - Detail (khusus yang disetujui): tabel pos x tahun berisi tautan file arsip; setiap unduhan dihitung.
 *
 * Perbedaan dengan aplikasi lama: tautan unduh per pos DAN per arsip (dulu semua tahun mengunduh file yang sama),
 * hanya pemohon/superadmin yang bisa membuka detail, ubah pengajuan dan survei kepuasan (dulu nonaktif) tidak dibuat.
 */
class UnduhDataController extends Controller
{
    public function pengajuan(Request $request): View
    {
        // whereHas('pos'): item untuk pos yang sudah dihapus (lunak) tidak ditampilkan; relasinya null.
        $item = PermintaanDataPos::with('permintaan', 'pos:id,nama,walidata_id', 'pos.walidata:id,nama')
            ->whereHas('pos')
            ->when(! $request->user()->isSuperadmin(), fn ($q) => $q->whereHas('permintaan', fn ($q) => $q->where('pengguna_id', $request->user()->id)))
            ->get();

        return view('admin.unduh-data.index', [
            'baris' => $this->paginasi($request, $this->kelompok($item)),
            'mode' => 'pengajuan',
        ]);
    }

    public function verifikasi(Request $request): View
    {
        $item = PermintaanDataPos::with('permintaan', 'pos:id,nama,walidata_id', 'pos.walidata:id,nama')
            ->whereIn('pos_id', Pos::terlihatOleh($request->user())->select('id'))
            ->get();

        return view('admin.unduh-data.index', [
            'baris' => $this->paginasi($request, $this->kelompok($item)),
            'mode' => 'verifikasi',
        ]);
    }

    public function create(): View
    {
        // Pos yang punya rentang ketersediaan data, dikelompokkan per DAS (aplikasi lama: pos_ketersediaan_awal terisi).
        $pos = Pos::whereNotNull('data_tersedia_dari')->orderBy('das')->orderBy('nama')
            ->get(['id', 'nama', 'das', 'walidata_id', 'data_tersedia_dari', 'data_tersedia_sampai']);

        return view('admin.unduh-data.form', [
            // Pos tanpa DAS dikelompokkan di akhir.
            'posPerDas' => $pos->sortBy(fn ($p) => $p->das ? 0 : 1)->groupBy(fn ($p) => $p->das ?: 'Tanpa DAS'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_pemohon' => ['required', 'string', 'max:150'],
            'instansi' => ['nullable', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:150'],
            'tujuan' => ['nullable', 'string', 'max:255'],
            'pos' => ['required', 'array', 'min:1'],
            'pos.*' => ['integer', Rule::exists('pos', 'id')->whereNotNull('data_tersedia_dari')->whereNull('deleted_at')],
        ], [
            'pos.required' => 'Pilih minimal satu pos.',
        ], [
            'nama_pemohon' => 'nama',
        ]);

        DB::transaction(function () use ($data, $request) {
            $permintaan = PermintaanData::create([
                'pengguna_id' => $request->user()->id,
                'kode_sesi' => (string) Str::ulid(),
                'nama_pemohon' => $data['nama_pemohon'],
                'instansi' => $data['instansi'] ?? null,
                'jabatan' => $data['jabatan'] ?? null,
                'tujuan' => $data['tujuan'] ?? null,
            ]);
            foreach (array_unique($data['pos']) as $posId) {
                $permintaan->item()->create(['pos_id' => $posId, 'status' => StatusPermintaan::Pengajuan, 'token_unduh' => Str::random(32)]);
            }
        });

        return to_route('admin.unduh.pengajuan')->with('pesan', 'Pengajuan data dikirim ke walidata pemilik pos.');
    }

    /** Hapus pengajuan untuk satu walidata (aplikasi lama: satu baris data_download). */
    public function destroy(Request $request, PermintaanData $permintaan, int $walidata): RedirectResponse
    {
        abort_unless($request->user()->isSuperadmin() || $permintaan->pengguna_id === $request->user()->id, 404);

        $permintaan->item()->whereHas('pos', fn ($q) => $q->where('walidata_id', $walidata))->delete();
        if (! $permintaan->item()->exists()) {
            $permintaan->delete();
        }

        return to_route('admin.unduh.pengajuan')->with('pesan', 'Pengajuan data dihapus.');
    }

    /** Setujui atau tutup permintaan untuk semua pos walidata ini (aplikasi lama: goverif). */
    public function proses(Request $request, PermintaanData $permintaan, int $walidata): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in([StatusPermintaan::Disetujui->value, StatusPermintaan::Ditutup->value])]])['status'];

        $jumlah = $permintaan->item()
            ->whereIn('pos_id', Pos::terlihatOleh($request->user())->where('walidata_id', $walidata)->select('id'))
            ->get()
            ->each(fn (PermintaanDataPos $i) => $i->update([
                'status' => $status,
                'token_unduh' => $i->token_unduh ?? Str::random(32),
                'diproses_oleh' => $request->user()->id,
                'diproses_at' => now(),
            ]))
            ->count();
        abort_if($jumlah === 0, 404);

        return to_route('admin.unduh.verifikasi')->with('pesan', $status === 'disetujui' ? 'Permintaan disetujui.' : 'Permintaan ditutup.');
    }

    public function detail(Request $request, PermintaanData $permintaan, int $walidata): View
    {
        abort_unless($request->user()->isSuperadmin() || $permintaan->pengguna_id === $request->user()->id, 404);

        $item = $permintaan->item()->with('pos:id,nama,walidata_id,das')
            ->whereHas('pos', fn ($q) => $q->where('walidata_id', $walidata))->get();
        abort_if($item->isEmpty(), 404);

        $arsip = ArsipPos::whereIn('pos_id', $item->pluck('pos_id'))->orderByDesc('tahun')->get();

        return view('admin.unduh-data.detail', [
            'permintaan' => $permintaan,
            'item' => $item->sortBy('pos.nama'),
            'disetujui' => $item->every(fn ($i) => $i->status === StatusPermintaan::Disetujui),
            'arsip' => $arsip->groupBy('pos_id')->map(fn ($a) => $a->keyBy('tahun')),
            'tahun' => $arsip->pluck('tahun')->unique()->sortDesc()->values(),
        ]);
    }

    /** Unduh satu file arsip lewat token item permintaan; dihitung seperti download_hitung lama. */
    public function unduh(Request $request, string $token, ArsipPos $arsip): StreamedResponse
    {
        $item = PermintaanDataPos::with('permintaan')->where('token_unduh', $token)->firstOrFail();
        abort_unless($request->user()->isSuperadmin() || $item->permintaan->pengguna_id === $request->user()->id, 404);
        abort_unless($item->status === StatusPermintaan::Disetujui && $arsip->pos_id === $item->pos_id, 404);
        abort_unless(Storage::disk('public')->exists($arsip->path), 404, 'File arsip belum tersedia di server.');

        $item->increment('jumlah_unduh');

        return Storage::disk('public')->download($arsip->path);
    }

    /**
     * Satu baris per permintaan x walidata, terbaru di atas.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function kelompok(Collection $item): Collection
    {
        return $item->groupBy(fn ($i) => $i->permintaan_data_id.'-'.$i->pos->walidata_id)
            ->map(fn ($k) => [
                'permintaan' => $k->first()->permintaan,
                'walidata_id' => $k->first()->pos->walidata_id,
                'walidata' => $k->first()->pos->walidata?->nama,
                'status' => $k->first()->status,
                'keterangan' => $k->pluck('keterangan')->filter()->unique()->implode('; '),
                'jumlahPos' => $k->count(),
            ])
            ->sortByDesc(fn ($b) => $b['permintaan']->id)
            ->values();
    }

    private function paginasi(Request $request, Collection $baris): LengthAwarePaginator
    {
        $halaman = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator($baris->forPage($halaman, 50)->values(), $baris->count(), 50, $halaman, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}

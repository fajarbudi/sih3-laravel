<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\PengamatanDebit;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengamatan debit manual di pos AWLR (asal: Debit.php). Jam 07, 12, 17; keempat nilai diisi manual
 * seperti aplikasi lama (tidak dihitung dari rumus debit).
 *
 * Perbedaan dengan aplikasi lama:
 * - Ubah dan hapus dibatasi pada pos yang boleh dilihat pengguna (dulu tidak dicek).
 * - Tombol verifikasi per baris diganti kotak centang "Tervalidasi" di form ubah (khusus admin);
 *   data baru tetap langsung tervalidasi seperti default kolom lama (awlr_status = 'Ya').
 * - Ekspor Excel/cetak dan unggah file belum dibuat.
 */
class DebitController extends Controller
{
    public const JAM = [7, 12, 17];

    public function index(Request $request): View
    {
        // Filter sama dengan aplikasi lama: walidata (khusus superadmin), pos, nama pos, jam, bulan.
        $filter = array_filter($request->validate([
            'walidata' => ['nullable', 'integer'],
            'pos' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
            'jam' => ['nullable', Rule::in(self::JAM)],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]), fn ($v) => $v !== null && $v !== '');
        $superadmin = $request->user()->isSuperadmin();

        $pos = $this->posTerlihat($request)
            ->when($superadmin ? ($filter['walidata'] ?? null) : null, fn ($q, $walidata) => $q->where('walidata_id', $walidata))
            ->when($filter['pos'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($filter['cari'] ?? null, fn ($q, $cari) => $q->where('nama', 'like', '%'.$cari.'%'));

        $data = PengamatanDebit::with('pos:id,nama')
            ->whereIn('pos_id', $pos->select('id'))
            ->when($filter['jam'] ?? null, fn ($q, $jam) => $q->whereTime('waktu', sprintf('%02d:00:00', $jam)))
            ->when($filter['bulan'] ?? null, function ($q, $bulan) {
                $awal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
                $q->whereBetween('waktu', [$awal, $awal->copy()->endOfMonth()]);
            })
            ->orderByDesc('id') // seperti aplikasi lama: terakhir diinput di atas
            ->paginate(50)
            ->withQueryString();

        return view('admin.debit.index', [
            'data' => $data,
            'daftarPos' => $this->daftarPos($request),
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new PengamatanDebit(['waktu' => today()->setHour(7)]));
    }

    public function store(Request $request): RedirectResponse
    {
        PengamatanDebit::create($this->validasi($request) + ['diinput_oleh' => $request->user()->id]);

        return to_route('admin.debit')->with('pesan', 'Data debit disimpan.');
    }

    public function edit(Request $request, PengamatanDebit $debit): View
    {
        $this->pastikanBoleh($request, $debit);

        return $this->form($request, $debit);
    }

    public function update(Request $request, PengamatanDebit $debit): RedirectResponse
    {
        $this->pastikanBoleh($request, $debit);
        $debit->update($this->validasi($request, $debit));

        return to_route('admin.debit')->with('pesan', 'Data debit diperbarui.');
    }

    public function destroy(Request $request, PengamatanDebit $debit): RedirectResponse
    {
        $this->pastikanBoleh($request, $debit);
        $debit->delete();

        return to_route('admin.debit')->with('pesan', 'Data debit dihapus.');
    }

    private function form(Request $request, PengamatanDebit $debit): View
    {
        return view('admin.debit.form', [
            'debit' => $debit,
            'daftarPos' => $this->daftarPos($request),
            'petugas' => $request->user()->peran === PeranPengguna::PetugasPos,
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?PengamatanDebit $debit = null): array
    {
        $petugas = $request->user()->peran === PeranPengguna::PetugasPos;

        // Petugas selalu mencatat untuk posnya sendiri, dan data baru hanya untuk hari ini (seperti aplikasi lama).
        if ($petugas) {
            $request->merge(['pos_id' => $request->user()->pos_id]);
            if (! $debit) {
                $request->merge(['tanggal' => today()->toDateString()]);
            }
        }

        $angka = ['nullable', 'numeric', 'between:-9999999,9999999']; // decimal(10,3)
        $data = $request->validate([
            'pos_id' => ['required', Rule::in($this->posTerlihat($request)->pluck('id'))],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jam' => ['required', Rule::in(self::JAM)],
            'intake_kanan' => $angka,
            'intake_kiri' => $angka,
            'limpas' => $angka,
            'debit' => $angka,
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'pos_id.in' => 'Pos tidak ditemukan atau bukan pos Anda.',
        ], [
            'pos_id' => 'pos',
            'intake_kanan' => 'debit intake kanan',
            'intake_kiri' => 'debit intake kiri',
            'limpas' => 'debit limpas',
        ]);

        if (! $petugas && $debit) {
            $data['tervalidasi'] = $request->boolean('tervalidasi');
        }

        $data['waktu'] = Carbon::parse($data['tanggal'])->setHour((int) $data['jam']);
        unset($data['tanggal'], $data['jam']);

        // Satu pengamatan per pos per jam (indeks unik pos_id + waktu).
        $sudahAda = PengamatanDebit::where(['pos_id' => $data['pos_id'], 'waktu' => $data['waktu']])
            ->when($debit, fn ($q) => $q->whereKeyNot($debit->id))
            ->exists();
        if ($sudahAda) {
            throw \Illuminate\Validation\ValidationException::withMessages(['jam' => 'Data pos ini untuk tanggal dan jam tersebut sudah ada.']);
        }

        return $data;
    }

    private function posTerlihat(Request $request)
    {
        return Pos::terlihatOleh($request->user())->jenis(JenisPos::Awlr);
    }

    private function daftarPos(Request $request)
    {
        return $this->posTerlihat($request)->orderBy('nama')->pluck('nama', 'id');
    }

    private function pastikanBoleh(Request $request, PengamatanDebit $debit): void
    {
        abort_unless($this->posTerlihat($request)->whereKey($debit->pos_id)->exists(), 404);
    }
}

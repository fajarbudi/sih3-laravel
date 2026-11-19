<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\PengamatanAwlr;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengamatan duga air manual (asal: Awlr.php). Petugas mencatat tiga kali sehari: jam 07, 12, 17.
 *
 * Perbedaan dengan aplikasi lama:
 * - Ubah dan hapus dibatasi pada pos yang boleh dilihat pengguna (dulu tidak dicek).
 * - Tombol verifikasi per baris diganti kotak centang "Tervalidasi" di form ubah (khusus admin);
 *   data baru tetap langsung tervalidasi seperti default kolom lama (awlr_status = 'Ya').
 * - Ekspor Excel/cetak dan unggah file belum dibuat.
 */
class DugaAirController extends Controller
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

        $data = PengamatanAwlr::with('pos:id,nama')
            ->whereIn('pos_id', $pos->select('id'))
            ->when($filter['jam'] ?? null, fn ($q, $jam) => $q->whereTime('waktu', sprintf('%02d:00:00', $jam)))
            ->when($filter['bulan'] ?? null, function ($q, $bulan) {
                $awal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
                $q->whereBetween('waktu', [$awal, $awal->copy()->endOfMonth()]);
            })
            ->orderByDesc('waktu')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.duga-air.index', [
            'data' => $data,
            'daftarPos' => $this->daftarPos($request),
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new PengamatanAwlr(['waktu' => today()->setHour(7)]));
    }

    public function store(Request $request): RedirectResponse
    {
        PengamatanAwlr::create($this->validasi($request) + ['diinput_oleh' => $request->user()->id]);

        return to_route('admin.duga-air')->with('pesan', 'Data duga air disimpan.');
    }

    public function edit(Request $request, PengamatanAwlr $dugaAir): View
    {
        $this->pastikanBoleh($request, $dugaAir);

        return $this->form($request, $dugaAir);
    }

    public function update(Request $request, PengamatanAwlr $dugaAir): RedirectResponse
    {
        $this->pastikanBoleh($request, $dugaAir);
        $dugaAir->update($this->validasi($request, $dugaAir));

        return to_route('admin.duga-air')->with('pesan', 'Data duga air diperbarui.');
    }

    public function destroy(Request $request, PengamatanAwlr $dugaAir): RedirectResponse
    {
        $this->pastikanBoleh($request, $dugaAir);
        $dugaAir->delete();

        return to_route('admin.duga-air')->with('pesan', 'Data duga air dihapus.');
    }

    private function form(Request $request, PengamatanAwlr $dugaAir): View
    {
        return view('admin.duga-air.form', [
            'dugaAir' => $dugaAir,
            'daftarPos' => $this->daftarPos($request),
            'petugas' => $request->user()->peran === PeranPengguna::PetugasPos,
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?PengamatanAwlr $dugaAir = null): array
    {
        $petugas = $request->user()->peran === PeranPengguna::PetugasPos;

        // Petugas selalu mencatat untuk posnya sendiri, dan data baru hanya untuk hari ini (seperti aplikasi lama).
        if ($petugas) {
            $request->merge(['pos_id' => $request->user()->pos_id]);
            if (! $dugaAir) {
                $request->merge(['tanggal' => today()->toDateString()]);
            }
        }

        $angka = ['nullable', 'numeric', 'between:-9999,9999'];
        $data = $request->validate([
            'pos_id' => ['required', Rule::in($this->posTerlihat($request)->pluck('id'))],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jam' => ['required', Rule::in(self::JAM)],
            'limpas' => $angka,
            'intake_kiri' => $angka,
            'intake_kanan' => $angka,
            'tma' => $angka,
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'pos_id.in' => 'Pos tidak ditemukan atau bukan pos Anda.',
        ], [
            'pos_id' => 'pos',
            'tma' => 'rerata TMA',
        ]);

        if (! $petugas && $dugaAir) {
            $data['tervalidasi'] = $request->boolean('tervalidasi');
        }

        $data['waktu'] = Carbon::parse($data['tanggal'])->setHour((int) $data['jam']);
        unset($data['tanggal'], $data['jam']);

        // Satu pengamatan per pos per jam (indeks unik pos_id + waktu).
        $sudahAda = PengamatanAwlr::where(['pos_id' => $data['pos_id'], 'waktu' => $data['waktu']])
            ->when($dugaAir, fn ($q) => $q->whereKeyNot($dugaAir->id))
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

    private function pastikanBoleh(Request $request, PengamatanAwlr $dugaAir): void
    {
        abort_unless($this->posTerlihat($request)->whereKey($dugaAir->pos_id)->exists(), 404);
    }
}

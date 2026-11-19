<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\PengamatanMat;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pengamatan muka air tanah manual (asal: Mat.php). Jam pengamatan 07, 12, 17.
 * Nilai negatif = kedalaman di bawah permukaan tanah (data lama -13 s.d. -5,2 m).
 *
 * Perbedaan dengan aplikasi lama sama seperti DugaAirController: ubah/hapus dibatasi pada pos
 * pengguna, validasi lewat kotak centang di form ubah (admin), ekspor Excel belum dibuat.
 */
class MukaAirTanahController extends Controller
{
    public const JAM = [7, 12, 17];

    public function index(Request $request): View
    {
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

        $data = PengamatanMat::with('pos:id,nama')
            ->whereIn('pos_id', $pos->select('id'))
            ->when($filter['jam'] ?? null, fn ($q, $jam) => $q->whereTime('waktu', sprintf('%02d:00:00', $jam)))
            ->when($filter['bulan'] ?? null, function ($q, $bulan) {
                $awal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
                $q->whereBetween('waktu', [$awal, $awal->copy()->endOfMonth()]);
            })
            ->orderByDesc('id') // seperti aplikasi lama: terakhir diinput di atas
            ->paginate(50)
            ->withQueryString();

        return view('admin.muka-air-tanah.index', [
            'data' => $data,
            'daftarPos' => $this->daftarPos($request),
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new PengamatanMat(['waktu' => today()->setHour(7)]));
    }

    public function store(Request $request): RedirectResponse
    {
        PengamatanMat::create($this->validasi($request) + ['diinput_oleh' => $request->user()->id]);

        return to_route('admin.muka-air-tanah')->with('pesan', 'Data muka air tanah disimpan.');
    }

    public function edit(Request $request, PengamatanMat $mukaAirTanah): View
    {
        $this->pastikanBoleh($request, $mukaAirTanah);

        return $this->form($request, $mukaAirTanah);
    }

    public function update(Request $request, PengamatanMat $mukaAirTanah): RedirectResponse
    {
        $this->pastikanBoleh($request, $mukaAirTanah);
        $mukaAirTanah->update($this->validasi($request, $mukaAirTanah));

        return to_route('admin.muka-air-tanah')->with('pesan', 'Data muka air tanah diperbarui.');
    }

    public function destroy(Request $request, PengamatanMat $mukaAirTanah): RedirectResponse
    {
        $this->pastikanBoleh($request, $mukaAirTanah);
        $mukaAirTanah->delete();

        return to_route('admin.muka-air-tanah')->with('pesan', 'Data muka air tanah dihapus.');
    }

    private function form(Request $request, PengamatanMat $mukaAirTanah): View
    {
        return view('admin.muka-air-tanah.form', [
            'mukaAirTanah' => $mukaAirTanah,
            'daftarPos' => $this->daftarPos($request),
            'petugas' => $request->user()->peran === PeranPengguna::PetugasPos,
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?PengamatanMat $mukaAirTanah = null): array
    {
        $petugas = $request->user()->peran === PeranPengguna::PetugasPos;

        // Petugas selalu mencatat untuk posnya sendiri, dan data baru hanya untuk hari ini (seperti aplikasi lama).
        if ($petugas) {
            $request->merge(['pos_id' => $request->user()->pos_id]);
            if (! $mukaAirTanah) {
                $request->merge(['tanggal' => today()->toDateString()]);
            }
        }

        $data = $request->validate([
            'pos_id' => ['required', Rule::in($this->posTerlihat($request)->pluck('id'))],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jam' => ['required', Rule::in(self::JAM)],
            'muka_air_tanah' => ['nullable', 'numeric', 'between:-9999,9999'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'pos_id.in' => 'Pos tidak ditemukan atau bukan pos Anda.',
        ], [
            'pos_id' => 'pos',
            'muka_air_tanah' => 'kedudukan MAT',
        ]);

        if (! $petugas && $mukaAirTanah) {
            $data['tervalidasi'] = $request->boolean('tervalidasi');
        }

        $data['waktu'] = Carbon::parse($data['tanggal'])->setHour((int) $data['jam']);
        unset($data['tanggal'], $data['jam']);

        // Satu pengamatan per pos per jam (indeks unik pos_id + waktu).
        $sudahAda = PengamatanMat::where(['pos_id' => $data['pos_id'], 'waktu' => $data['waktu']])
            ->when($mukaAirTanah, fn ($q) => $q->whereKeyNot($mukaAirTanah->id))
            ->exists();
        if ($sudahAda) {
            throw ValidationException::withMessages(['jam' => 'Data pos ini untuk tanggal dan jam tersebut sudah ada.']);
        }

        return $data;
    }

    private function posTerlihat(Request $request)
    {
        return Pos::terlihatOleh($request->user())->jenis(JenisPos::Mat);
    }

    private function daftarPos(Request $request)
    {
        return $this->posTerlihat($request)->orderBy('nama')->pluck('nama', 'id');
    }

    private function pastikanBoleh(Request $request, PengamatanMat $mukaAirTanah): void
    {
        abort_unless($this->posTerlihat($request)->whereKey($mukaAirTanah->pos_id)->exists(), 404);
    }
}

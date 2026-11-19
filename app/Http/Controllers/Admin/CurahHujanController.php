<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\PengamatanArr;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pengamatan curah hujan manual (asal: Arr.php). Jam pengamatan sama dengan duga air: 07, 12, 17.
 *
 * Perbedaan dengan aplikasi lama sama seperti DugaAirController: ubah/hapus dibatasi pada pos
 * pengguna, validasi lewat kotak centang di form ubah (admin), ekspor Excel belum dibuat.
 */
class CurahHujanController extends Controller
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

        $data = PengamatanArr::with('pos:id,nama')
            ->whereIn('pos_id', $pos->select('id'))
            ->when($filter['jam'] ?? null, fn ($q, $jam) => $q->whereTime('waktu', sprintf('%02d:00:00', $jam)))
            ->when($filter['bulan'] ?? null, function ($q, $bulan) {
                $awal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
                $q->whereBetween('waktu', [$awal, $awal->copy()->endOfMonth()]);
            })
            ->orderByDesc('id') // seperti aplikasi lama: terakhir diinput di atas
            ->paginate(50)
            ->withQueryString();

        return view('admin.curah-hujan.index', [
            'data' => $data,
            'daftarPos' => $this->daftarPos($request),
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new PengamatanArr(['waktu' => today()->setHour(7)]));
    }

    public function store(Request $request): RedirectResponse
    {
        PengamatanArr::create($this->validasi($request) + ['diinput_oleh' => $request->user()->id]);

        return to_route('admin.curah-hujan')->with('pesan', 'Data curah hujan disimpan.');
    }

    public function edit(Request $request, PengamatanArr $curahHujan): View
    {
        $this->pastikanBoleh($request, $curahHujan);

        return $this->form($request, $curahHujan);
    }

    public function update(Request $request, PengamatanArr $curahHujan): RedirectResponse
    {
        $this->pastikanBoleh($request, $curahHujan);
        $curahHujan->update($this->validasi($request, $curahHujan));

        return to_route('admin.curah-hujan')->with('pesan', 'Data curah hujan diperbarui.');
    }

    public function destroy(Request $request, PengamatanArr $curahHujan): RedirectResponse
    {
        $this->pastikanBoleh($request, $curahHujan);
        $curahHujan->delete();

        return to_route('admin.curah-hujan')->with('pesan', 'Data curah hujan dihapus.');
    }

    private function form(Request $request, PengamatanArr $curahHujan): View
    {
        return view('admin.curah-hujan.form', [
            'curahHujan' => $curahHujan,
            'daftarPos' => $this->daftarPos($request),
            'petugas' => $request->user()->peran === PeranPengguna::PetugasPos,
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?PengamatanArr $curahHujan = null): array
    {
        $petugas = $request->user()->peran === PeranPengguna::PetugasPos;

        // Petugas selalu mencatat untuk posnya sendiri, dan data baru hanya untuk hari ini (seperti aplikasi lama).
        if ($petugas) {
            $request->merge(['pos_id' => $request->user()->pos_id]);
            if (! $curahHujan) {
                $request->merge(['tanggal' => today()->toDateString()]);
            }
        }

        $data = $request->validate([
            'pos_id' => ['required', Rule::in($this->posTerlihat($request)->pluck('id'))],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jam' => ['required', Rule::in(self::JAM)],
            'hujan' => ['nullable', 'numeric', 'between:0,999999'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'pos_id.in' => 'Pos tidak ditemukan atau bukan pos Anda.',
        ], [
            'pos_id' => 'pos',
            'hujan' => 'curah hujan',
        ]);

        if (! $petugas && $curahHujan) {
            $data['tervalidasi'] = $request->boolean('tervalidasi');
        }

        $data['waktu'] = Carbon::parse($data['tanggal'])->setHour((int) $data['jam']);
        unset($data['tanggal'], $data['jam']);

        // Satu pengamatan per pos per jam (indeks unik pos_id + waktu).
        $sudahAda = PengamatanArr::where(['pos_id' => $data['pos_id'], 'waktu' => $data['waktu']])
            ->when($curahHujan, fn ($q) => $q->whereKeyNot($curahHujan->id))
            ->exists();
        if ($sudahAda) {
            throw ValidationException::withMessages(['jam' => 'Data pos ini untuk tanggal dan jam tersebut sudah ada.']);
        }

        return $data;
    }

    private function posTerlihat(Request $request)
    {
        return Pos::terlihatOleh($request->user())->jenis(JenisPos::Arr);
    }

    private function daftarPos(Request $request)
    {
        return $this->posTerlihat($request)->orderBy('nama')->pluck('nama', 'id');
    }

    private function pastikanBoleh(Request $request, PengamatanArr $curahHujan): void
    {
        abort_unless($this->posTerlihat($request)->whereKey($curahHujan->pos_id)->exists(), 404);
    }
}

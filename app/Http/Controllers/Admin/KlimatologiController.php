<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\PengamatanKlimatologi;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pengamatan klimatologi manual (asal: Klimat.php). Jam pengamatan 07, 12, 17.
 *
 * Perbedaan dengan aplikasi lama sama seperti DugaAirController: ubah/hapus dibatasi pada pos
 * pengguna, validasi lewat kotak centang di form ubah (admin), ekspor Excel belum dibuat.
 */
class KlimatologiController extends Controller
{
    public const JAM = [7, 12, 17];

    /** Kolom nilai => label tabel, urutan dan label sama dengan aplikasi lama. */
    public const NILAI = [
        'kelembapan' => 'RH',
        'suhu_max' => 'Temperatur Maksimal',
        'suhu_min' => 'Temperatur Minimal',
        'suhu_rata' => 'Temperatur Rata-rata',
        'suhu_air' => 'Temperatur Air',
        'suhu_air_min' => 'Temperatur Air Minimum',
        'suhu_air_max' => 'Temperatur Air Maximum',
        'penguapan' => 'Penguapan',
        'kecepatan_angin' => 'Kecepatan Angin',
        'radiasi' => 'Radiasi Matahari',
        'penyinaran' => 'Lama Penyinaran',
        'hujan' => 'Hujan',
    ];

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

        $data = PengamatanKlimatologi::with('pos:id,nama')
            ->whereIn('pos_id', $pos->select('id'))
            ->when($filter['jam'] ?? null, fn ($q, $jam) => $q->whereTime('waktu', sprintf('%02d:00:00', $jam)))
            ->when($filter['bulan'] ?? null, function ($q, $bulan) {
                $awal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
                $q->whereBetween('waktu', [$awal, $awal->copy()->endOfMonth()]);
            })
            ->orderByDesc('id') // seperti aplikasi lama: terakhir diinput di atas
            ->paginate(50)
            ->withQueryString();

        return view('admin.klimatologi.index', [
            'data' => $data,
            'daftarPos' => $this->daftarPos($request),
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            'filter' => $filter,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new PengamatanKlimatologi(['waktu' => today()->setHour(7)]));
    }

    public function store(Request $request): RedirectResponse
    {
        PengamatanKlimatologi::create($this->validasi($request) + ['diinput_oleh' => $request->user()->id]);

        return to_route('admin.klimatologi')->with('pesan', 'Data klimatologi disimpan.');
    }

    public function edit(Request $request, PengamatanKlimatologi $klimatologi): View
    {
        $this->pastikanBoleh($request, $klimatologi);

        return $this->form($request, $klimatologi);
    }

    public function update(Request $request, PengamatanKlimatologi $klimatologi): RedirectResponse
    {
        $this->pastikanBoleh($request, $klimatologi);
        $klimatologi->update($this->validasi($request, $klimatologi));

        return to_route('admin.klimatologi')->with('pesan', 'Data klimatologi diperbarui.');
    }

    public function destroy(Request $request, PengamatanKlimatologi $klimatologi): RedirectResponse
    {
        $this->pastikanBoleh($request, $klimatologi);
        $klimatologi->delete();

        return to_route('admin.klimatologi')->with('pesan', 'Data klimatologi dihapus.');
    }

    private function form(Request $request, PengamatanKlimatologi $klimatologi): View
    {
        return view('admin.klimatologi.form', [
            'klimatologi' => $klimatologi,
            'daftarPos' => $this->daftarPos($request),
            'petugas' => $request->user()->peran === PeranPengguna::PetugasPos,
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?PengamatanKlimatologi $klimatologi = null): array
    {
        $petugas = $request->user()->peran === PeranPengguna::PetugasPos;

        // Petugas selalu mencatat untuk posnya sendiri, dan data baru hanya untuk hari ini (seperti aplikasi lama).
        if ($petugas) {
            $request->merge(['pos_id' => $request->user()->pos_id]);
            if (! $klimatologi) {
                $request->merge(['tanggal' => today()->toDateString()]);
            }
        }

        $aturanNilai = array_map(fn () => ['nullable', 'numeric', 'between:-9999,9999'], self::NILAI);
        $aturanNilai['radiasi'] = ['nullable', 'numeric', 'between:-9999,99999999'];
        // Kecepatan angin berisi bacaan counter anemometer yang terus naik (mis. 2.158.290), seperti di aplikasi lama.
        $aturanNilai['kecepatan_angin'] = ['nullable', 'numeric', 'between:-9999,999999999999'];

        $data = $request->validate([
            'pos_id' => ['required', Rule::in($this->posTerlihat($request)->pluck('id'))],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jam' => ['required', Rule::in(self::JAM)],
            ...$aturanNilai,
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'pos_id.in' => 'Pos tidak ditemukan atau bukan pos Anda.',
        ], [
            'pos_id' => 'pos',
            ...array_map('strtolower', self::NILAI),
        ]);

        if (! $petugas && $klimatologi) {
            $data['tervalidasi'] = $request->boolean('tervalidasi');
        }

        $data['waktu'] = Carbon::parse($data['tanggal'])->setHour((int) $data['jam']);
        unset($data['tanggal'], $data['jam']);

        // Satu pengamatan per pos per jam (indeks unik pos_id + waktu).
        $sudahAda = PengamatanKlimatologi::where(['pos_id' => $data['pos_id'], 'waktu' => $data['waktu']])
            ->when($klimatologi, fn ($q) => $q->whereKeyNot($klimatologi->id))
            ->exists();
        if ($sudahAda) {
            throw ValidationException::withMessages(['jam' => 'Data pos ini untuk tanggal dan jam tersebut sudah ada.']);
        }

        return $data;
    }

    private function posTerlihat(Request $request)
    {
        return Pos::terlihatOleh($request->user())->jenis(JenisPos::Klimatologi);
    }

    private function daftarPos(Request $request)
    {
        return $this->posTerlihat($request)->orderBy('nama')->pluck('nama', 'id');
    }

    private function pastikanBoleh(Request $request, PengamatanKlimatologi $klimatologi): void
    {
        abort_unless($this->posTerlihat($request)->whereKey($klimatologi->pos_id)->exists(), 404);
    }
}

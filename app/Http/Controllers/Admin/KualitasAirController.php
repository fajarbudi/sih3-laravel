<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\ParameterKualitasAir;
use App\Models\PengamatanKualitasAir;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Hasil uji kualitas air per sampel (asal: Kualitas_air.php).
 *
 * Seperti aplikasi lama: tanpa jam (hanya tanggal), boleh beberapa sampel per pos per hari,
 * petugas selalu untuk posnya sendiri, nilai parameter dengan 4 desimal, plus kondisi fisik lapangan (teks).
 *
 * Perbedaan dengan aplikasi lama:
 * - Nilai parameter disimpan per baris di nilai_kualitas_air (dulu 65 kolom).
 * - Index pencemaran tidak lagi disalin ke "nilai terakhir" pos; ambil dari sampel terbaru bila perlu.
 * - Teks tidak lagi diubah ke Huruf Kapital Tiap Kata (ucwords).
 * - Ubah/hapus dibatasi pada pos pengguna; validasi lewat kotak centang (admin); ekspor Excel belum dibuat.
 */
class KualitasAirController extends Controller
{
    /** Kondisi fisik lapangan (teks bebas), label sama dengan aplikasi lama. */
    public const FISIK = [
        'pengukuran_debit' => 'Pengukuran Debit',
        'cuaca' => 'Cuaca',
        'hanyutan' => 'Bahan Hanyutan',
        'sedimen' => 'Sediment',
        'bau' => 'Bau',
        'kotoran_minyak' => 'Kotoran Minyak',
        'lumut' => 'Lumut',
        'vegetasi' => 'Vegetasi',
        'keadaan_sungai' => 'Keadaan Sungai',
    ];

    public function index(Request $request): View
    {
        // Filter seperti aplikasi lama: walidata (khusus superadmin), pos, bulan, dan teks (nama pos atau lokasi).
        $filter = array_filter($request->validate([
            'walidata' => ['nullable', 'integer'],
            'pos' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]), fn ($v) => $v !== null && $v !== '');
        $superadmin = $request->user()->isSuperadmin();

        $pos = $this->posTerlihat($request)
            ->when($superadmin ? ($filter['walidata'] ?? null) : null, fn ($q, $walidata) => $q->where('walidata_id', $walidata))
            ->when($filter['pos'] ?? null, fn ($q, $id) => $q->whereKey($id));

        $data = PengamatanKualitasAir::with('pos:id,nama,walidata_id', 'pos.walidata:id,nama')
            ->whereIn('pos_id', $pos->select('id'))
            ->when($filter['cari'] ?? null, fn ($q, $cari) => $q->where(fn ($q) => $q
                ->where('lokasi', 'like', '%'.$cari.'%')
                ->orWhereHas('pos', fn ($q) => $q->where('nama', 'like', '%'.$cari.'%'))))
            ->when($filter['bulan'] ?? null, function ($q, $bulan) {
                $awal = Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
                $q->whereBetween('waktu', [$awal, $awal->copy()->endOfMonth()]);
            })
            ->orderByDesc('id') // seperti aplikasi lama: terakhir diinput di atas
            ->paginate(50)
            ->withQueryString();

        return view('admin.kualitas-air.index', [
            'data' => $data,
            'daftarPos' => $this->daftarPos($request),
            'daftarWalidata' => $superadmin ? Walidata::orderBy('nama')->pluck('nama', 'id') : collect(),
            'filter' => $filter,
            'petugas' => $request->user()->peran === PeranPengguna::PetugasPos,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new PengamatanKualitasAir(['waktu' => today()]));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $nilai] = $this->validasi($request);

        DB::transaction(function () use ($data, $nilai, $request) {
            $sampel = PengamatanKualitasAir::create($data + ['diinput_oleh' => $request->user()->id]);
            $this->simpanNilai($sampel, $nilai);
        });

        return to_route('admin.kualitas-air')->with('pesan', 'Data kualitas air disimpan.');
    }

    public function edit(Request $request, PengamatanKualitasAir $kualitasAir): View
    {
        $this->pastikanBoleh($request, $kualitasAir);

        return $this->form($request, $kualitasAir);
    }

    public function update(Request $request, PengamatanKualitasAir $kualitasAir): RedirectResponse
    {
        $this->pastikanBoleh($request, $kualitasAir);
        [$data, $nilai] = $this->validasi($request, $kualitasAir);

        DB::transaction(function () use ($kualitasAir, $data, $nilai) {
            $kualitasAir->update($data);
            $this->simpanNilai($kualitasAir, $nilai);
        });

        return to_route('admin.kualitas-air')->with('pesan', 'Data kualitas air diperbarui.');
    }

    public function destroy(Request $request, PengamatanKualitasAir $kualitasAir): RedirectResponse
    {
        $this->pastikanBoleh($request, $kualitasAir);
        $kualitasAir->delete(); // nilai_kualitas_air ikut terhapus (cascade)

        return to_route('admin.kualitas-air')->with('pesan', 'Data kualitas air dihapus.');
    }

    private function form(Request $request, PengamatanKualitasAir $kualitasAir): View
    {
        return view('admin.kualitas-air.form', [
            'kualitasAir' => $kualitasAir,
            'parameter' => $this->parameter(),
            'nilaiLama' => $kualitasAir->exists ? $kualitasAir->nilaiPerKode() : [],
            'daftarPos' => $this->daftarPos($request),
            'petugas' => $request->user()->peran === PeranPengguna::PetugasPos,
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: array<int, float>} data sampel, dan nilai per id parameter */
    private function validasi(Request $request, ?PengamatanKualitasAir $kualitasAir = null): array
    {
        $petugas = $request->user()->peran === PeranPengguna::PetugasPos;
        if ($petugas) {
            $request->merge(['pos_id' => $request->user()->pos_id]);
        }

        $parameter = $this->parameter();
        $teks = ['nullable', 'string', 'max:255'];
        $data = $request->validate([
            'pos_id' => ['required', Rule::in($this->posTerlihat($request)->pluck('id'))],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'lokasi' => $teks,
            'nilai' => ['nullable', 'array'],
            'nilai.*' => ['nullable', 'numeric', 'between:-999999999999,999999999999'], // decimal(16,4)
            'indeks_pencemaran' => ['nullable', 'numeric', 'between:-999999,999999'],
            ...array_map(fn () => $teks, self::FISIK),
            'keterangan' => $teks,
        ], [
            'pos_id.in' => 'Pos tidak ditemukan atau bukan pos Anda.',
            'nilai.*.numeric' => 'Nilai harus berupa angka.',
        ], [
            'pos_id' => 'pos',
            'lokasi' => 'lokasi pengamatan',
            'indeks_pencemaran' => 'index pencemaran',
        ]);

        // Hanya parameter yang dikenal; kolom kosong tidak disimpan.
        $nilai = [];
        foreach ($parameter as $p) {
            $v = $data['nilai'][$p->kode] ?? null;
            if ($v !== null) {
                $nilai[$p->id] = (float) $v;
            }
        }

        if (! $petugas && $kualitasAir) {
            $data['tervalidasi'] = $request->boolean('tervalidasi');
        }

        // Tanpa jam seperti aplikasi lama; jam data lama (mis. 07.00) dipertahankan saat diubah.
        $data['waktu'] = Carbon::parse($data['tanggal'])->setTimeFrom($kualitasAir?->waktu ?? today());
        unset($data['tanggal'], $data['nilai']);

        return [$data, $nilai];
    }

    /** @param  array<int, float>  $nilai */
    private function simpanNilai(PengamatanKualitasAir $sampel, array $nilai): void
    {
        // Hanya nilai parameter aktif yang diganti; nilai parameter nonaktif (tidak ada di form) dibiarkan.
        $sampel->nilai()->whereIn('parameter_kualitas_air_id', $this->parameter()->modelKeys())->delete();
        $sampel->nilai()->createMany(collect($nilai)->map(fn ($v, $id) => ['parameter_kualitas_air_id' => $id, 'nilai' => $v])->values());
    }

    private function parameter()
    {
        return ParameterKualitasAir::where('aktif', true)->orderBy('urutan')->get();
    }

    private function posTerlihat(Request $request)
    {
        return Pos::terlihatOleh($request->user())->jenis(JenisPos::KualitasAir);
    }

    private function daftarPos(Request $request)
    {
        return $this->posTerlihat($request)->orderBy('nama')->pluck('nama', 'id');
    }

    private function pastikanBoleh(Request $request, PengamatanKualitasAir $kualitasAir): void
    {
        abort_unless($this->posTerlihat($request)->whereKey($kualitasAir->pos_id)->exists(), 404);
    }
}

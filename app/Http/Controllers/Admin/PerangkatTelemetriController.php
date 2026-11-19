<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SumberTelemetri;
use App\Http\Controllers\Controller;
use App\Models\PerangkatTelemetri;
use App\Models\Pos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pemetaan perangkat telemetri ke pos (asal: Postele.php). Aplikasi lama hanya memetakan logger Beacon
 * (ref_pos.pos_tele + pos_sensor) dari daftar yang diambil langsung ke API aplikasi hidrologi.
 * Di sini semua sumber (Beacon, PJT, Tatonas, Stesy, Higertech, PCB Jogja, Sermo) dikelola di perangkat_telemetri:
 * sumber, kode perangkat, sensor, pos, faktor skala, aktif; plus status data terakhir yang diterima.
 * Khusus superadmin (menu Pengaturan).
 */
class PerangkatTelemetriController extends Controller
{
    public function index(Request $request): View
    {
        $filter = array_filter($request->validate([
            'sumber' => ['nullable', Rule::enum(SumberTelemetri::class)],
            'aktif' => ['nullable', Rule::in(['1', '0'])],
            'cari' => ['nullable', 'string', 'max:100'],
        ]), fn ($v) => $v !== null && $v !== '');

        $data = PerangkatTelemetri::with(['pos' => fn ($q) => $q->withTrashed()->select('id', 'nama', 'jenis', 'deleted_at')])
            ->when($filter['sumber'] ?? null, fn ($q, $s) => $q->where('sumber', $s))
            ->when(isset($filter['aktif']), fn ($q) => $q->where('aktif', $filter['aktif']))
            ->when($filter['cari'] ?? null, fn ($q, $c) => $q->where(fn ($q) => $q->where('kode_perangkat', 'like', '%'.$c.'%')
                ->orWhereHas('pos', fn ($q) => $q->where('nama', 'like', '%'.$c.'%'))))
            ->orderBy('sumber')->orderBy('kode_perangkat')
            ->paginate(50)->withQueryString();

        return view('admin.perangkat-telemetri.index', compact('data', 'filter'));
    }

    public function create(): View
    {
        return $this->form(new PerangkatTelemetri(['faktor_skala' => 1, 'aktif' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        PerangkatTelemetri::create($this->validasi($request));

        return to_route('admin.perangkat-telemetri')->with('pesan', 'Perangkat telemetri disimpan.');
    }

    public function edit(PerangkatTelemetri $perangkat): View
    {
        return $this->form($perangkat);
    }

    public function update(Request $request, PerangkatTelemetri $perangkat): RedirectResponse
    {
        $perangkat->update($this->validasi($request, $perangkat));

        return to_route('admin.perangkat-telemetri')->with('pesan', 'Perangkat telemetri diperbarui.');
    }

    public function destroy(PerangkatTelemetri $perangkat): RedirectResponse
    {
        $perangkat->delete(); // data telemetri yang sudah masuk tetap tersimpan (terikat ke pos, bukan perangkat)

        return to_route('admin.perangkat-telemetri')->with('pesan', 'Perangkat telemetri dihapus.');
    }

    private function form(PerangkatTelemetri $perangkat): View
    {
        return view('admin.perangkat-telemetri.form', [
            'perangkat' => $perangkat,
            'daftarPos' => Pos::orderBy('nama')->get(['id', 'nama', 'jenis']),
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?PerangkatTelemetri $perangkat = null): array
    {
        $data = $request->validate([
            'sumber' => ['required', Rule::enum(SumberTelemetri::class)],
            'kode_perangkat' => ['required', 'string', 'max:100',
                // Satu perangkat (sumber + kode) boleh dipetakan ke beberapa pos, tapi tidak dua kali ke pos yang sama.
                Rule::unique('perangkat_telemetri')->where(fn ($q) => $q->where('sumber', $request->input('sumber'))->where('pos_id', $request->input('pos_id')))->ignore($perangkat)],
            'pos_id' => ['required', Rule::exists('pos', 'id')->whereNull('deleted_at')],
            'sensor' => ['nullable', 'string', 'max:50'],
            'faktor_skala' => ['required', 'numeric', 'between:0.000001,999999'],
        ], [
            'kode_perangkat.unique' => 'Perangkat ini sudah dipetakan ke pos tersebut.',
        ], [
            'kode_perangkat' => 'kode perangkat',
            'pos_id' => 'pos',
            'faktor_skala' => 'faktor skala',
        ]);
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}

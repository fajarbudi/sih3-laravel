<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PeranPengguna;
use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Models\Pos;
use App\Models\Walidata;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Akun pengguna panel admin (asal: Pengguna.php). Seperti aplikasi lama: daftar urut nama dengan kolom
 * Nama, Nama Login, Walidata, POS, Email, Telpon, Level; tambah/ubah/hapus; sandi dikosongkan = tidak diubah.
 *
 * Perbedaan dengan aplikasi lama: sandi di-hash bcrypt (dulu md5) dan minimal 8 karakter; walidata wajib untuk
 * admin walidata dan pos wajib untuk petugas; ada status aktif (akun nonaktif tidak bisa masuk), paginasi dan
 * filter; akun sendiri tidak bisa dihapus, dinonaktifkan, atau diubah levelnya.
 */
class PenggunaController extends Controller
{
    public function index(Request $request): View
    {
        $filter = array_filter($request->validate([
            'peran' => ['nullable', Rule::enum(PeranPengguna::class)],
            'walidata' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]), fn ($v) => $v !== null && $v !== '');

        $data = Pengguna::with('walidata:id,nama', 'pos:id,nama')
            ->when($filter['peran'] ?? null, fn ($q, $p) => $q->where('peran', $p))
            ->when($filter['walidata'] ?? null, fn ($q, $w) => $q->where('walidata_id', $w))
            ->when($filter['cari'] ?? null, fn ($q, $c) => $q->where(fn ($q) => $q->where('nama', 'like', '%'.$c.'%')->orWhere('username', 'like', '%'.$c.'%')))
            ->orderBy('nama')
            ->paginate(50)->withQueryString();

        return view('admin.pengguna.index', [
            'data' => $data,
            'filter' => $filter,
            'daftarWalidata' => Walidata::orderBy('nama')->pluck('nama', 'id'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Pengguna(['peran' => PeranPengguna::PetugasPos, 'aktif' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        Pengguna::create($this->validasi($request));

        return to_route('admin.pengguna')->with('pesan', 'Pengguna disimpan.');
    }

    public function edit(Pengguna $pengguna): View
    {
        return $this->form($pengguna);
    }

    public function update(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $pengguna->update($this->validasi($request, $pengguna));

        return to_route('admin.pengguna')->with('pesan', 'Pengguna diperbarui.');
    }

    public function destroy(Request $request, Pengguna $pengguna): RedirectResponse
    {
        abort_if($pengguna->is($request->user()), 422, 'Akun sendiri tidak bisa dihapus.');
        $pengguna->delete();

        return to_route('admin.pengguna')->with('pesan', 'Pengguna dihapus.');
    }

    private function form(Pengguna $pengguna): View
    {
        return view('admin.pengguna.form', [
            'pengguna' => $pengguna,
            'daftarWalidata' => Walidata::orderBy('nama')->pluck('nama', 'id'),
            'daftarPos' => Pos::orderBy('nama')->get(['id', 'nama', 'walidata_id', 'jenis']),
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Pengguna $pengguna = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('pengguna')->ignore($pengguna)],
            'password' => [$pengguna ? 'nullable' : 'required', Password::min(8)],
            'peran' => ['required', Rule::enum(PeranPengguna::class)],
            'walidata_id' => ['nullable', 'required_if:peran,'.PeranPengguna::AdminWalidata->value, Rule::exists('walidata', 'id')],
            'pos_id' => ['nullable', 'required_if:peran,'.PeranPengguna::PetugasPos->value, Rule::exists('pos', 'id')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:150'],
            'telepon' => ['nullable', 'string', 'max:50'],
        ], [
            'walidata_id.required_if' => 'Walidata wajib dipilih untuk admin walidata.',
            'pos_id.required_if' => 'Pos wajib dipilih untuk petugas pos.',
        ], [
            'username' => 'nama login',
            'password' => 'sandi',
            'walidata_id' => 'walidata',
            'pos_id' => 'pos',
        ]);

        // Level akun sendiri tidak bisa diubah (agar superadmin tidak kehilangan akses ke menu Pengaturan).
        if ($pengguna?->is($request->user()) && $data['peran'] !== $pengguna->peran->value) {
            throw ValidationException::withMessages(['peran' => 'Level akun Anda sendiri tidak bisa diubah.']);
        }

        // Petugas mengikuti walidata posnya; superadmin tidak terikat walidata/pos.
        if ($data['peran'] === PeranPengguna::PetugasPos->value) {
            $data['walidata_id'] = Pos::whereKey($data['pos_id'])->value('walidata_id');
        } elseif ($data['peran'] === PeranPengguna::Superadmin->value) {
            $data['walidata_id'] = $data['pos_id'] = null;
        } else {
            $data['pos_id'] = null;
        }

        // Akun sendiri tidak bisa dinonaktifkan (agar tidak terkunci).
        $data['aktif'] = $pengguna?->is($request->user()) ? true : $request->boolean('aktif');

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password_md5'] = null; // sandi md5 lama tidak berlaku lagi
        }

        return $data;
    }
}

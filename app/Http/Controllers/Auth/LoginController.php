<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Maksimal 5 percobaan gagal per menit per username + IP.
        $kunci = Str::lower($data['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            return back()->withInput()->withErrors(['username' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.']);
        }

        $pengguna = Pengguna::where('username', $data['username'])->first();
        $cocok = $pengguna && (
            ($pengguna->password && Hash::check($data['password'], $pengguna->password))
            || $pengguna->cocokkanPasswordLama($data['password']) // akun lama: MD5, diubah ke bcrypt saat login pertama
        );

        if (! $cocok || ! $pengguna->aktif) {
            RateLimiter::hit($kunci);

            return back()->withInput()->withErrors(['username' => $cocok ? 'Akun tidak aktif.' : 'Username atau password salah.']);
        }

        RateLimiter::clear($kunci);
        Auth::login($pengguna, $request->boolean('ingat'));
        $request->session()->regenerate();
        $pengguna->forceFill(['terakhir_login_at' => now()])->save();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

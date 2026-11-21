{{--
    Halaman masuk panel pengelola, gaya GLASSMORPHISM (permintaan user 2026-11-22). Susunan tetap dari login lama
    (kartu dua bagian: kiri identitas portal, kanan formulir, tombol kuning) di atas foto bendung yang jernih.
    Kartu = kaca buram bernuansa navy (backdrop-blur + tint) agar teks putih tetap terbaca di atas bagian foto yang terang;
    tepi berkilau tipis, isian kaca, tombol kuning padat. Ponsel: bagian identitas menjadi kepala kartu.
    Tombol lihat kata sandi hanya muncul bila JavaScript aktif.
--}}
@php
    $isian = 'block h-[3.125rem] w-full rounded-2xl border-0 bg-white/10 pl-12 pr-4 text-[15px] text-white ring-1 ring-inset placeholder:text-white/55 hover:bg-white/15 focus:bg-white/15 focus:outline-none focus:ring-2 focus:ring-[#ffd400]';
    $fitur = [['bi-broadcast-pin', 'Data pos & telemetri'], ['bi-journal-text', 'Buletin & publikasi'], ['bi-images', 'Galeri']];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Masuk · Portal SIH3 Wilayah Sungai Serayu Opak</title>
    <script>document.documentElement.classList.add('js')</script>
    <x-layouts.tailwind :fonts="['inter', 'jakarta']" />
</head>
<body class="relative isolate min-h-screen bg-primary-950 font-portal text-white antialiased">
    <img src="{{ asset('images/portal/hero-1920.jpg') }}" srcset="{{ asset('images/portal/hero-960.jpg') }} 960w, {{ asset('images/portal/hero-1920.jpg') }} 1920w"
        sizes="100vw" alt="" width="1920" height="1102" class="fixed inset-0 -z-20 size-full object-cover">
    <div class="fixed inset-0 -z-10 bg-linear-to-br from-primary-950/45 via-primary-950/20 to-primary-950/55" aria-hidden="true"></div>
    {{-- Cahaya warna di belakang kaca agar efek buramnya terasa. --}}
    <div class="fixed left-[12%] top-[18%] -z-10 size-80 rounded-full bg-primary-500/40 blur-3xl" aria-hidden="true"></div>
    <div class="fixed bottom-[12%] right-[14%] -z-10 size-72 rounded-full bg-[#ffd400]/25 blur-3xl" aria-hidden="true"></div>

    <main class="flex min-h-screen items-center justify-center px-3 py-6 sm:px-4 sm:py-12">
        <div class="relative grid w-full max-w-[58rem] overflow-hidden rounded-[2rem] bg-primary-950/35 shadow-[0_30px_80px_-20px_rgb(0_0_0/0.55)] ring-1 ring-inset ring-white/25 backdrop-blur-2xl backdrop-saturate-150 md:grid-cols-[minmax(0,0.95fr)_minmax(0,1fr)]">
            {{-- Kilau tepi atas kaca. --}}
            <span class="pointer-events-none absolute inset-x-10 top-0 h-px bg-linear-to-r from-transparent via-white/70 to-transparent" aria-hidden="true"></span>

            {{-- Kiri / atas: identitas portal. --}}
            <section class="relative flex flex-col justify-between gap-6 border-b border-white/15 p-6 sm:p-7 md:min-h-[33rem] md:border-b-0 md:border-r md:p-10">
                <a href="{{ route('beranda') }}" class="w-fit rounded-lg" aria-label="Portal SIH3 Wilayah Sungai Serayu Opak, beranda">
                    <img src="{{ asset('images/logo-header-admin.png') }}" alt="Portal SIH3 Wilayah Sungai Serayu Opak" width="256" height="73" class="h-10 w-auto brightness-0 invert md:h-11">
                </a>

                <div class="[text-shadow:0_1px_12px_rgb(6_26_53/0.4)]">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1 text-xs font-bold uppercase tracking-[0.16em] text-[#ffd400] ring-1 ring-inset ring-white/20 [text-shadow:none]">
                        <span class="size-1.5 rounded-full bg-[#ffd400]" aria-hidden="true"></span>Panel Pengelola
                    </span>
                    <p class="mt-4 text-xl font-extrabold leading-tight tracking-tight md:text-3xl">
                        Sistem Informasi Hidrologi
                        <span class="mt-1 block text-[15px] font-semibold text-white/85 md:text-lg">Hidrometeorologi &amp; Hidrogeologi</span>
                    </p>
                    <ul class="mt-6 hidden flex-wrap gap-2 [text-shadow:none] md:flex">
                        @foreach ($fitur as [$ikon, $teks])
                            <li class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white/90 ring-1 ring-inset ring-white/15">
                                <i class="bi {{ $ikon }} text-sky-200" aria-hidden="true"></i>{{ $teks }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="hidden text-xs text-white/65 md:block">&copy; {{ date('Y') }} BBWS Serayu Opak · Wilayah Sungai Serayu Opak</p>
            </section>

            {{-- Kanan / bawah: formulir masuk di atas kaca. --}}
            <section class="flex items-center px-6 py-7 sm:px-8 md:px-12 md:py-12">
                <div class="w-full">
                    <span class="hidden size-12 items-center justify-center rounded-2xl bg-white/10 text-xl text-[#ffd400] ring-1 ring-inset ring-white/20 md:flex" aria-hidden="true">
                        <i class="bi bi-shield-lock"></i>
                    </span>
                    <h1 class="text-[1.75rem] font-extrabold tracking-tight md:mt-5">Masuk</h1>
                    <p class="mt-1 text-[15px] text-white/75">Silakan masuk dengan akun pengelola Anda.</p>

                    <form method="POST" action="{{ route('login') }}" id="form-masuk" class="mt-7 space-y-5">
                        @csrf
                        <div>
                            <label for="username" class="text-sm font-semibold text-white/90">Nama login</label>
                            <div class="relative mt-2">
                                <i class="bi bi-person pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-white/70" aria-hidden="true"></i>
                                <input id="username" name="username" type="text" value="{{ old('username') }}" placeholder="Masukkan nama login" required autofocus autocomplete="username" @error('username') aria-invalid="true" aria-describedby="username-error" @enderror
                                    @class([$isian, 'ring-red-300' => $errors->has('username'), 'ring-white/20' => ! $errors->has('username')])>
                            </div>
                            @error('username')
                                <p id="username-error" class="mt-2 flex items-center gap-1.5 rounded-xl bg-red-500/20 px-3 py-2 text-sm text-red-50 ring-1 ring-inset ring-red-300/40"><i class="bi bi-exclamation-circle" aria-hidden="true"></i>{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="text-sm font-semibold text-white/90">Kata sandi</label>
                            <div class="relative mt-2">
                                <i class="bi bi-lock pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-white/70" aria-hidden="true"></i>
                                <input id="password" name="password" type="password" placeholder="Masukkan kata sandi" required autocomplete="current-password" class="{{ $isian }} ring-white/20 [.js_&]:pr-12">
                                <button type="button" id="lihat-sandi" aria-label="Tampilkan kata sandi" aria-pressed="false"
                                    class="absolute right-1.5 top-1/2 hidden size-10 -translate-y-1/2 items-center justify-center rounded-xl text-white/70 hover:bg-white/10 hover:text-white [.js_&]:flex">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <label class="flex min-h-10 w-fit cursor-pointer items-center gap-2.5 text-sm text-white/80">
                            <input type="checkbox" name="ingat" value="1" class="size-4 rounded accent-[#ffd400]">
                            Ingat saya
                        </label>

                        <button type="submit" id="tombol-masuk" class="group flex h-[3.25rem] w-full items-center justify-center gap-2 rounded-2xl bg-[#ffd400] px-4 text-[15px] font-bold text-[#0b2545] shadow-[0_12px_28px_-10px_rgb(255_212_0/0.7)] hover:bg-[#ffdc2e] active:bg-[#f0c800] disabled:cursor-wait disabled:opacity-80">
                            Masuk <i class="bi bi-arrow-right transition group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden="true"></i>
                        </button>
                    </form>

                    <p class="mt-6 text-center text-xs text-white/65 md:hidden">&copy; {{ date('Y') }} BBWS Serayu Opak</p>
                </div>
            </section>
        </div>
    </main>

    <script>
        // Tampilkan/sembunyikan kata sandi.
        const sandi = document.getElementById('password');
        const tombolSandi = document.getElementById('lihat-sandi');
        tombolSandi.addEventListener('click', () => {
            const lihat = sandi.type === 'password';
            sandi.type = lihat ? 'text' : 'password';
            tombolSandi.setAttribute('aria-pressed', String(lihat));
            tombolSandi.setAttribute('aria-label', lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            tombolSandi.querySelector('i').className = lihat ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
        // Cegah klik ganda saat mengirim (seperti aplikasi lama).
        document.getElementById('form-masuk').addEventListener('submit', () => {
            const t = document.getElementById('tombol-masuk');
            t.disabled = true;
            t.textContent = 'Memproses…';
        });
    </script>
</body>
</html>

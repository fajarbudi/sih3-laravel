{{--
    Tata letak portal publik, mengikuti portal live lama (sih3.bbwsserayuopak.id) yang dinilai user lebih bagus:
    Plus Jakarta Sans, header transparan di atas foto hero (logo dan menu putih) yang menjadi putih saat digulir,
    tombol Masuk kuning, kaki ringkas biru tua.
    Menu mengikuti menu AKTIF portal lama: Beranda, Geospasial (Pos Pemantauan; Delineasi DTA dan FloodRouting = aplikasi
    terpisah), Walidata (website, PPID, WhatsApp per walidata dari database), Masuk. Menu Informasi H3 dan Publikasi
    ditambahkan saat halamannya dibuat (tidak ada tautan ke halaman yang belum ada).
    Dropdown memakai <details>: bisa dipakai dengan papan ketik tanpa skrip; skrip kecil hanya menutup dropdown lain/klik di luar.

    Pakai: <x-layouts.portal judul="..." deskripsi="..." :hero-gelap="true">isi</x-layouts.portal>
    hero-gelap: halaman diawali hero foto gelap; header transparan selama berada di atasnya.
--}}
@props(['judul' => null, 'heroGelap' => false, 'deskripsi' => 'Portal SIH3 Wilayah Sungai Serayu Opak menyajikan data hidrologi, hidrometeorologi, dan hidrogeologi: tinggi muka air, curah hujan, klimatologi, muka air tanah, dan kualitas air.'])
@php
    $geospasial = [
        ['Pos Pemantauan', route('beranda').'#peta', false],
        ['Delineasi DTA', 'https://delineasi.bbwsserayuopak.id/', true],
        ['FloodRouting', 'https://flood-routing-bbwsso.vercel.app', true],
    ];
    $tautanMenu = 'flex min-h-11 items-center gap-2 rounded-lg px-3 text-gray-700 hover:bg-gray-50 hover:text-primary-700';
    $panel = 'absolute right-0 top-full z-[1100] mt-2 w-72 rounded-xl border border-gray-100 bg-white p-1.5 text-gray-900 shadow-xl shadow-gray-950/10';
    // Warna menu: gelap di header putih, putih di atas hero (group-data-[atas]/kepala).
    $warnaMenu = 'text-gray-700 hover:bg-gray-100 hover:text-gray-950 group-data-[atas]/kepala:text-white group-data-[atas]/kepala:hover:bg-white/10';
    $tombolMenu = "flex min-h-10 list-none items-center gap-1 rounded-full px-4 font-semibold $warnaMenu [&::-webkit-details-marker]:hidden";
    $urlMasuk = auth()->check() ? route('admin.dashboard') : route('login');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul ? "$judul · " : '' }}Portal SIH3 Wilayah Sungai Serayu Opak</title>
    <meta name="description" content="{{ $deskripsi }}">
    {{-- Penanda skrip aktif (varian [.js_&]): mis. tab bidang di beranda; tanpa skrip semua panel tampil. --}}
    <script>document.documentElement.classList.add('js')</script>
    <x-layouts.tailwind :fonts="['inter', 'jakarta']" />
    @stack('kepala')
</head>
<body class="flex min-h-screen flex-col bg-white font-portal text-base text-gray-900 antialiased">
    <a href="#isi" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[1200] focus:rounded-md focus:bg-white focus:px-3 focus:py-2 focus:shadow">Lewati ke isi</a>

    <header id="kepala" @if ($heroGelap) data-transparan data-atas @endif
        class="group/kepala sticky top-0 z-[1000] border-b border-gray-100 bg-white/95 backdrop-blur transition-colors data-[atas]:border-transparent data-[atas]:bg-transparent data-[atas]:backdrop-blur-none">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-4 lg:h-[4.5rem] lg:px-8">
            <a href="{{ route('beranda') }}" class="shrink-0" aria-label="Portal SIH3 Wilayah Sungai Serayu Opak, beranda">
                <img src="{{ asset('images/logo-header-admin.png') }}" alt="" width="256" height="73" class="h-9 w-auto group-data-[atas]/kepala:brightness-0 group-data-[atas]/kepala:invert lg:h-10">
            </a>

            {{-- Navigasi layar lebar --}}
            <nav aria-label="Menu utama" class="ml-auto hidden items-center gap-1 lg:flex">
                <a href="{{ route('beranda') }}" @if (request()->routeIs('beranda')) aria-current="page" @endif
                    class="flex min-h-10 items-center rounded-full px-4 font-semibold {{ $warnaMenu }}">Beranda</a>
                <details class="relative" data-dropdown>
                    <summary class="{{ $tombolMenu }}">Geospasial <i class="bi bi-chevron-down text-xs" aria-hidden="true"></i></summary>
                    <div class="{{ $panel }}">
                        @foreach ($geospasial as [$teks, $url, $luar])
                            <a href="{{ $url }}" @if ($luar) target="_blank" rel="noopener" @endif class="{{ $tautanMenu }}">
                                {{ $teks }}
                                @if ($luar) <i class="bi bi-box-arrow-up-right ml-auto text-xs text-gray-600" aria-hidden="true"></i><span class="sr-only">(aplikasi lain, tab baru)</span> @endif
                            </a>
                        @endforeach
                    </div>
                </details>
                @if ($menuWalidata->isNotEmpty())
                    <details class="relative" data-dropdown>
                        <summary class="{{ $tombolMenu }}">Walidata <i class="bi bi-chevron-down text-xs" aria-hidden="true"></i></summary>
                        <div class="{{ $panel }} max-h-[70vh] w-80 overflow-y-auto">
                            @include('portal.menu-walidata', ['tautanMenu' => $tautanMenu])
                        </div>
                    </details>
                @endif
                <a href="{{ $urlMasuk }}" class="ml-3 flex min-h-11 items-center rounded-full bg-amber-400 px-6 font-bold text-gray-950 hover:bg-amber-300 active:bg-amber-500">
                    {{ auth()->check() ? 'Panel admin' : 'Masuk' }}
                </a>
            </nav>

            {{-- Navigasi ponsel --}}
            <details class="relative ml-auto lg:hidden" data-dropdown>
                <summary class="flex size-11 list-none items-center justify-center rounded-full text-gray-800 hover:bg-gray-100 group-data-[atas]/kepala:text-white group-data-[atas]/kepala:hover:bg-white/10 [&::-webkit-details-marker]:hidden" aria-label="Buka menu">
                    <i class="bi bi-list text-2xl" aria-hidden="true"></i>
                </summary>
                <nav aria-label="Menu utama" class="fixed inset-x-0 top-16 z-[1100] max-h-[calc(100vh-4rem)] overflow-y-auto rounded-b-2xl border-b border-gray-100 bg-white px-4 py-3 text-gray-900 shadow-xl shadow-gray-950/10">
                    <a href="{{ route('beranda') }}" class="{{ $tautanMenu }} font-semibold">Beranda</a>
                    <p class="mt-3 px-3 text-sm font-semibold text-gray-600">Geospasial</p>
                    @foreach ($geospasial as [$teks, $url, $luar])
                        <a href="{{ $url }}" @if ($luar) target="_blank" rel="noopener" @endif class="{{ $tautanMenu }}">
                            {{ $teks }} @if ($luar) <i class="bi bi-box-arrow-up-right ml-auto text-xs text-gray-600" aria-hidden="true"></i><span class="sr-only">(aplikasi lain, tab baru)</span> @endif
                        </a>
                    @endforeach
                    @if ($menuWalidata->isNotEmpty())
                        <p class="mt-3 px-3 text-sm font-semibold text-gray-600">Walidata</p>
                        @include('portal.menu-walidata', ['tautanMenu' => $tautanMenu])
                    @endif
                    <a href="{{ $urlMasuk }}" class="mt-3 flex min-h-11 items-center justify-center rounded-full bg-amber-400 px-4 font-bold text-gray-950 hover:bg-amber-300">
                        {{ auth()->check() ? 'Panel admin' : 'Masuk' }}
                    </a>
                </nav>
            </details>
        </div>
    </header>

    <main id="isi" class="flex-1">
        {{ $slot }}
    </main>

    {{-- Kaki ringkas seperti portal lama: logo SIH3, nama balai, alamat; hak cipta di kanan. --}}
    <footer class="bg-primary-950 text-sm text-white/80">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-6 px-4 py-8 lg:px-8">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/portal/logo-sih3-serayu-opak.png') }}" alt="" width="256" height="338" class="h-11 w-auto shrink-0">
                <div>
                    <p class="font-bold text-white">Balai Besar Wilayah Sungai Serayu-Bogowonto &amp; Progo-Opak-Serang</p>
                    <address class="not-italic">Jl. Solo Km. 6, Caturtunggal, Depok, Sleman, D.I. Yogyakarta 55281</address>
                </div>
            </div>
            <p>&copy; {{ now()->year }} Portal SIH3, powered by <strong class="font-bold text-amber-300">Anauri</strong></p>
        </div>
    </footer>

    <script>
        // Satu dropdown terbuka sekaligus; klik di luar atau Escape menutupnya.
        const dropdown = [...document.querySelectorAll('[data-dropdown]')];
        dropdown.forEach(d => d.addEventListener('toggle', () => d.open && dropdown.forEach(lain => lain !== d && (lain.open = false))));
        document.addEventListener('click', e => dropdown.forEach(d => !d.contains(e.target) && (d.open = false)));
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            const terbuka = dropdown.find(d => d.open);
            if (terbuka) { terbuka.open = false; terbuka.querySelector('summary').focus(); }
        });

        // Header transparan hanya selama di atas hero gelap; setelah digulir menjadi putih.
        const kepala = document.getElementById('kepala');
        if (kepala.hasAttribute('data-transparan')) {
            const atur = () => kepala.toggleAttribute('data-atas', scrollY < 40);
            addEventListener('scroll', atur, { passive: true });
            atur();
        }
    </script>
    @stack('skrip')
</body>
</html>

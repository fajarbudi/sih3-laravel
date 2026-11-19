{{--
    Tata letak panel admin ala Linear:
    - sidebar abu-abu dengan menu workspace (pengguna + Keluar) di kiri atas,
    - konten di panel putih bergaris tipis, header 44px berisi breadcrumb dan tombol aksi,
    - bar filter opsional di bawah header, pesan sukses sebagai toast.

    Pakai: <x-layouts.admin judul="Duga Air" induk="Hidrologi" :penuh="true">
               <x-slot:aksi>...tombol...</x-slot:aksi>
               <x-slot:filter>...kontrol filter...</x-slot:filter>
               isi
           </x-layouts.admin>
    penuh = isi menempel ke tepi panel (untuk daftar/tabel), tanpa padding.
--}}
@props(['judul' => null, 'induk' => null, 'indukUrl' => null, 'keterangan' => null, 'penuh' => false])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $judul ? "$judul · " : '' }}Portal SIH3 Wilayah Sungai Serayu Opak</title>
    <x-layouts.tailwind />
</head>
<body class="min-h-screen bg-gray-100 font-sans text-sm text-gray-900 antialiased">
    @php $pengguna = auth()->user(); @endphp

    <div id="latar-sidebar" class="fixed inset-0 z-30 hidden bg-gray-950/30 lg:hidden" onclick="tutupSidebar()"></div>

    {{-- Sidebar: di layar kecil disembunyikan (hidden) agar tidak ikut terjangkau Tab. --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col bg-gray-100 max-lg:border-r max-lg:border-gray-200 lg:flex">
        {{-- Menu workspace: logo, nama aplikasi, dan pengguna --}}
        <details id="menu-workspace" class="relative mx-2 mt-2">
            <summary class="flex min-h-11 list-none items-center gap-2 rounded-md px-2 hover:bg-gray-200/70 active:bg-gray-200 lg:min-h-8 [&::-webkit-details-marker]:hidden">
                <img src="{{ asset('images/sih3-favicon.png') }}" alt="" width="20" height="20" class="size-5 shrink-0 rounded">
                <span class="truncate font-semibold">Portal SIH3</span>
                <i class="bi bi-chevron-down shrink-0 text-[0.625rem] text-gray-600" aria-hidden="true"></i>
                <span class="sr-only">Menu akun {{ $pengguna->nama }}</span>
                <span class="ml-auto flex size-5 shrink-0 items-center justify-center rounded-full bg-primary-500 text-[0.625rem] font-semibold text-white" aria-hidden="true">
                    {{ Str::upper(Str::substr($pengguna->nama, 0, 1)) }}
                </span>
            </summary>
            <div class="absolute inset-x-0 top-full z-50 mt-1 rounded-lg border border-gray-200 bg-white p-1 shadow-lg shadow-gray-950/5">
                <div class="px-2 py-1.5">
                    <p class="truncate font-medium">{{ $pengguna->nama }}</p>
                    <p class="truncate text-xs text-gray-600">{{ $pengguna->peran->label() }}</p>
                </div>
                <div class="my-1 border-t border-gray-200"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex min-h-11 w-full items-center gap-2 rounded-md px-2 text-left text-gray-700 hover:bg-gray-100 hover:text-gray-900 lg:min-h-8">
                        <i class="bi bi-box-arrow-right text-gray-600" aria-hidden="true"></i> Keluar
                    </button>
                </form>
            </div>
        </details>

        <x-admin.sidebar-menu />

        <p class="px-4 py-3 text-xs leading-relaxed text-gray-600">Portal SIH3 Wilayah Sungai Serayu Opak &copy; {{ date('Y') }} BBWS Serayu Opak</p>
    </aside>

    {{-- Panel konten --}}
    <div class="lg:py-2 lg:pr-2 lg:pl-60">
        <div class="flex min-h-screen flex-col bg-white lg:min-h-[calc(100vh-1rem)] lg:rounded-lg lg:border lg:border-gray-200 lg:shadow-xs">
            <header class="sticky top-0 z-20 flex h-11 shrink-0 items-center gap-2 border-b border-gray-200 bg-white/95 px-4 backdrop-blur lg:rounded-t-lg lg:pl-6 lg:pr-4">
                <button type="button" id="tombol-menu" class="-ml-2 flex size-11 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 active:bg-gray-200 lg:hidden"
                    onclick="bukaSidebar()" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                    <i class="bi bi-list text-xl leading-none" aria-hidden="true"></i>
                </button>
                <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-1.5">
                    @if ($induk)
                        @if ($indukUrl)
                            <a href="{{ $indukUrl }}" class="-mx-1 flex min-h-11 shrink-0 items-center rounded px-1 text-gray-600 hover:text-gray-900 active:text-gray-900 lg:min-h-7">{{ $induk }}</a>
                        @else
                            <span class="shrink-0 text-gray-600 max-sm:hidden">{{ $induk }}</span>
                        @endif
                        <i class="bi bi-chevron-right text-[0.625rem] text-gray-400 @unless ($indukUrl) max-sm:hidden @endunless" aria-hidden="true"></i>
                    @endif
                    <h1 class="truncate font-medium">{{ $judul }}</h1>
                </nav>
                @if ($keterangan)
                    <p class="min-w-0 truncate pl-2 text-gray-600 max-md:hidden">{{ $keterangan }}</p>
                @endif
                @isset($aksi)
                    <div class="ml-auto flex shrink-0 items-center gap-2">{{ $aksi }}</div>
                @endisset
            </header>

            @isset($filter)
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-2 lg:px-6">{{ $filter }}</div>
            @endisset

            <main @class(['min-w-0 flex-1', 'px-4 py-6 lg:px-6' => ! $penuh, 'flex flex-col' => $penuh])>
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Toast pesan sukses, pojok kanan bawah seperti Linear --}}
    @if (session('pesan'))
        <div id="toast" role="status" class="fixed bottom-4 right-4 z-50 flex max-w-[calc(100vw-2rem)] items-center gap-2.5 rounded-lg border border-gray-200 bg-white py-2.5 pl-3 pr-2 shadow-lg shadow-gray-950/10 motion-safe:transition-opacity motion-safe:duration-300">
            <i class="bi bi-check-circle-fill text-primary-500" aria-hidden="true"></i>
            <span>{{ session('pesan') }}</span>
            <button type="button" onclick="this.parentElement.remove()" class="flex size-11 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:size-6" aria-label="Tutup pesan">
                <i class="bi bi-x-lg text-xs" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    <x-admin.konfirmasi-hapus />

    <script>
        const sidebar = document.getElementById('sidebar');
        const latar = document.getElementById('latar-sidebar');
        const tombolMenu = document.getElementById('tombol-menu');
        const menuWorkspace = document.getElementById('menu-workspace');

        function bukaSidebar() {
            sidebar.classList.replace('hidden', 'flex');
            latar.classList.remove('hidden');
            tombolMenu.setAttribute('aria-expanded', 'true');
            sidebar.querySelector('summary')?.focus();
        }

        function tutupSidebar() {
            if (window.matchMedia('(min-width: 1024px)').matches) return;
            sidebar.classList.replace('flex', 'hidden');
            latar.classList.add('hidden');
            tombolMenu.setAttribute('aria-expanded', 'false');
            tombolMenu.focus();
        }

        // Menu workspace tertutup saat klik di luar.
        document.addEventListener('click', (e) => {
            if (menuWorkspace.open && !menuWorkspace.contains(e.target)) menuWorkspace.open = false;
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (menuWorkspace.open) { menuWorkspace.open = false; menuWorkspace.querySelector('summary').focus(); return; }
                if (!latar.classList.contains('hidden')) tutupSidebar();
            }
        });

        // Toast hilang sendiri setelah 4 detik.
        const toast = document.getElementById('toast');
        if (toast) setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
    </script>
    @stack('skrip')
</body>
</html>

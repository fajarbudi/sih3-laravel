@props(['judul' => null, 'keterangan' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $judul ? "$judul · " : '' }}Portal SIH3 Wilayah Sungai Serayu Opak</title>
    <x-layouts.tailwind />
</head>
{{-- Tata letak ala Linear: sidebar dan latar abu-abu yang sama, konten di panel putih bergaris tipis. --}}
<body class="min-h-screen bg-gray-100 font-sans text-sm text-gray-900 antialiased">
    @php $pengguna = auth()->user(); @endphp

    <div id="latar-sidebar" class="fixed inset-0 z-30 hidden bg-gray-950/40 lg:hidden" onclick="tutupSidebar()"></div>

    {{-- Sidebar: di layar kecil disembunyikan (hidden) agar tidak ikut terjangkau Tab. --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col bg-gray-100 max-lg:border-r max-lg:border-gray-200 lg:flex">
        <a href="{{ route('admin.dashboard') }}" class="mx-3 mt-3 flex h-12 shrink-0 items-center rounded-md px-2 hover:bg-gray-200/60">
            <img src="{{ asset('images/logo-header-admin.png') }}" alt="Portal SIH3 Wilayah Sungai Serayu Opak" width="256" height="73" class="h-8 w-auto">
        </a>
        <x-admin.sidebar-menu />

        {{-- Pengguna di bawah sidebar, seperti Linear --}}
        <div class="flex items-center gap-2.5 border-t border-gray-200 px-5 py-3">
            <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-300 text-xs font-semibold text-gray-700" aria-hidden="true">
                {{ Str::upper(Str::substr($pengguna->nama, 0, 1)) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium">{{ $pengguna->nama }}</p>
                <p class="truncate text-xs text-gray-600">{{ $pengguna->peran->label() }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex min-h-11 items-center rounded-md px-2 text-gray-600 hover:bg-gray-200/60 hover:text-gray-900 active:bg-gray-200 lg:min-h-8">Keluar</button>
            </form>
        </div>
    </aside>

    {{-- Panel konten --}}
    <div class="lg:py-2 lg:pr-2 lg:pl-60">
        <div class="flex min-h-screen flex-col bg-white lg:min-h-[calc(100vh-1rem)] lg:rounded-lg lg:ring-1 lg:ring-gray-200">
            <header class="sticky top-0 z-20 flex h-12 shrink-0 items-center gap-2 border-b border-gray-200 bg-white px-4 lg:rounded-t-lg lg:px-6">
                <button type="button" id="tombol-menu" class="-ml-2 flex size-11 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 active:bg-gray-200 lg:hidden"
                    onclick="bukaSidebar()" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                    <i class="bi bi-list text-xl leading-none" aria-hidden="true"></i>
                </button>
                <h1 class="shrink-0 font-medium">{{ $judul }}</h1>
                @if ($keterangan)
                    <p class="ml-auto min-w-0 truncate text-gray-500">{{ $keterangan }}</p>
                @endif
            </header>

            <main class="min-w-0 flex-1 px-4 py-6 lg:px-6">
                {{ $slot }}
            </main>

            <x-admin.footer />
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const latar = document.getElementById('latar-sidebar');
        const tombolMenu = document.getElementById('tombol-menu');

        function bukaSidebar() {
            sidebar.classList.replace('hidden', 'flex');
            latar.classList.remove('hidden');
            tombolMenu.setAttribute('aria-expanded', 'true');
            sidebar.querySelector('a')?.focus();
        }

        function tutupSidebar() {
            if (window.matchMedia('(min-width: 1024px)').matches) return;
            sidebar.classList.replace('flex', 'hidden');
            latar.classList.add('hidden');
            tombolMenu.setAttribute('aria-expanded', 'false');
            tombolMenu.focus();
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !latar.classList.contains('hidden')) tutupSidebar();
        });
    </script>
    @stack('skrip')
</body>
</html>

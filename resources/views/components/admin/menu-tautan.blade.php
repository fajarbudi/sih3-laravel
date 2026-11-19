{{--
    Satu item menu sidebar. Bila route-nya belum dibuat, tampil sebagai item non-aktif berlabel "Segera"
    (bukan tautan mati), dan otomatis menjadi tautan setelah route didaftarkan di routes/web.php.
    Pakai: <x-admin.menu-tautan route="admin.duga-air">Duga Air</x-admin.menu-tautan>
    Ikon opsional (nama Bootstrap Icons tanpa awalan "bi-"): ikon="droplet"
--}}
@props(['route', 'ikon' => null])

@php
    $ada = Route::has($route);
    $aktif = $ada && request()->routeIs($route, $route.'.*');
@endphp

<li>
    @if ($ada)
        <a href="{{ route($route) }}" @if ($aktif) aria-current="page" @endif @class([
            'flex min-h-11 items-center gap-2 rounded-md px-2 font-medium lg:min-h-7',
            'bg-gray-200/80 text-gray-900' => $aktif,
            'text-gray-700 hover:bg-gray-200/60 hover:text-gray-900 active:bg-gray-200' => ! $aktif,
        ])>
            @if ($ikon)
                <i @class(["bi bi-$ikon flex w-4 shrink-0 justify-center text-sm leading-none", 'text-gray-900' => $aktif, 'text-gray-600' => ! $aktif]) aria-hidden="true"></i>
            @endif
            <span class="truncate">{{ $slot }}</span>
        </a>
    @else
        <span class="flex min-h-11 cursor-not-allowed items-center gap-2 rounded-md px-2 font-medium text-gray-600 lg:min-h-7" aria-disabled="true">
            @if ($ikon)
                <i class="bi bi-{{ $ikon }} flex w-4 shrink-0 justify-center text-sm leading-none text-gray-400" aria-hidden="true"></i>
            @endif
            <span class="flex-1 truncate">{{ $slot }}</span>
            <span class="shrink-0 rounded px-1 text-[0.6875rem] leading-4 text-gray-600 ring-1 ring-gray-300">Segera</span>
        </span>
    @endif
</li>

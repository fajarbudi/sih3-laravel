{{--
    Satu item menu sidebar. Bila route-nya belum dibuat, tampil sebagai item non-aktif berlabel "Segera"
    (bukan tautan mati), dan otomatis menjadi tautan setelah route didaftarkan di routes/web.php.
    Pakai: <x-admin.menu-tautan route="admin.duga-air">Duga Air</x-admin.menu-tautan>
    Ikon opsional (nama Bootstrap Icons tanpa awalan "bi-"): ikon="droplet"
--}}
@props(['route', 'ikon' => null])

@php
    $ada = Route::has($route);
    $aktif = $ada && request()->routeIs($route.'*');
@endphp

<li>
    @if ($ada)
        <a href="{{ route($route) }}" @if ($aktif) aria-current="page" @endif @class([
            'flex min-h-11 items-center gap-2.5 rounded-md px-2 lg:min-h-8',
            'bg-white font-medium text-gray-900 ring-1 ring-gray-200' => $aktif,
            'text-gray-700 hover:bg-gray-200/60 hover:text-gray-900 active:bg-gray-200' => ! $aktif,
            'font-medium' => $ikon && ! $aktif,
        ])>
            @if ($ikon)
                <i @class(["bi bi-$ikon shrink-0 text-base leading-none", 'text-primary-600' => $aktif, 'text-gray-500' => ! $aktif]) aria-hidden="true"></i>
            @endif
            {{ $slot }}
        </a>
    @else
        <span @class(['flex min-h-11 cursor-not-allowed items-center gap-2.5 rounded-md px-2 text-gray-600 lg:min-h-8', 'font-medium' => $ikon]) aria-disabled="true">
            @if ($ikon)
                <i class="bi bi-{{ $ikon }} shrink-0 text-base leading-none text-gray-400" aria-hidden="true"></i>
            @endif
            <span class="flex-1 truncate">{{ $slot }}</span>
            <span class="shrink-0 text-xs text-gray-600">Segera</span>
        </span>
    @endif
</li>

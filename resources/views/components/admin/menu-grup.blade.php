{{--
    Grup menu yang bisa dibuka-tutup. Terbuka otomatis bila salah satu item di dalamnya adalah halaman aktif.
    Pakai: <x-admin.menu-grup label="Hidrologi" ikon="droplet"> ...<x-admin.menu-tautan>... </x-admin.menu-grup>
    ikon = nama Bootstrap Icons tanpa awalan "bi-".
--}}
@props(['label', 'ikon'])

<li>
    <details class="group" @if (str_contains($slot, 'aria-current="page"')) open @endif>
        <summary class="flex min-h-11 list-none items-center gap-2.5 rounded-md px-2 font-medium text-gray-700 hover:bg-gray-200/60 hover:text-gray-900 active:bg-gray-200 lg:min-h-8 [&::-webkit-details-marker]:hidden">
            <i class="bi bi-{{ $ikon }} shrink-0 text-base leading-none text-gray-500" aria-hidden="true"></i>
            <span class="flex-1">{{ $label }}</span>
            <i class="bi bi-chevron-right inline-block shrink-0 text-xs leading-none text-gray-500 transition-transform group-open:rotate-90 motion-reduce:transition-none" aria-hidden="true"></i>
        </summary>
        <ul class="mb-1 ml-[1.0625rem] mt-0.5 space-y-0.5 border-l border-gray-200 pl-2">
            {{ $slot }}
        </ul>
    </details>
</li>

{{--
    Grup menu yang bisa dibuka-tutup. Terbuka otomatis bila salah satu item di dalamnya adalah halaman aktif.
    Pakai: <x-admin.menu-grup label="Hidrologi" ikon="droplet"> ...<x-admin.menu-tautan>... </x-admin.menu-grup>
    ikon = nama Bootstrap Icons tanpa awalan "bi-".
--}}
@props(['label', 'ikon'])

<li>
    <details class="group" @if (str_contains($slot, 'aria-current="page"')) open @endif>
        <summary class="flex min-h-11 list-none items-center gap-2 rounded-md px-2 font-medium text-gray-700 hover:bg-gray-200/60 hover:text-gray-900 active:bg-gray-200 lg:min-h-7 [&::-webkit-details-marker]:hidden">
            <i class="bi bi-{{ $ikon }} flex w-4 shrink-0 justify-center text-sm leading-none text-gray-600" aria-hidden="true"></i>
            <span class="flex-1 truncate">{{ $label }}</span>
            <i class="bi bi-caret-right-fill inline-block shrink-0 text-[0.5rem] leading-none text-gray-500 transition-transform group-open:rotate-90 motion-reduce:transition-none" aria-hidden="true"></i>
        </summary>
        {{-- Sub-menu menjorok tanpa garis pandu, seperti daftar tim di Linear --}}
        <ul class="mt-px space-y-px pl-6">
            {{ $slot }}
        </ul>
    </details>
</li>

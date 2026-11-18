{{-- Kartu datar bergaris tipis (gaya Linear). Pakai: <x-admin.kartu judul="..." keterangan="...">isi</x-admin.kartu> --}}
@props(['judul' => null, 'keterangan' => null])

<section {{ $attributes->class('rounded-lg border border-gray-200 bg-white') }}>
    @if ($judul)
        <header class="px-4 pt-4">
            <h2 class="font-medium">{{ $judul }}</h2>
            @if ($keterangan)
                <p class="mt-0.5 text-gray-500">{{ $keterangan }}</p>
            @endif
        </header>
    @endif
    {{ $slot }}
</section>

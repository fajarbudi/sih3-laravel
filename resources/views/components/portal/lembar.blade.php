{{--
    Isi halaman dalam portal: lembar putih bersudut bulat yang naik menutupi bawah hero, dengan pil label, judul,
    keterangan, dan slot `kanan` (mis. jumlah data) sejajar judul.

    Pakai: <x-portal.lembar label="Buletin" ikon="bi-newspaper" judul="Edisi terbaru" keterangan="...">isi</x-portal.lembar>
--}}
@props(['label', 'ikon', 'judul', 'keterangan' => null])

<section aria-labelledby="judul-lembar" class="relative -mt-10 rounded-t-[2rem] bg-white pb-16 pt-10 sm:-mt-14 sm:rounded-t-[2.5rem] lg:pt-14">
    <div class="mx-auto max-w-7xl px-4 lg:px-8">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-primary-700 ring-1 ring-inset ring-primary-100">
            <i class="bi {{ $ikon }}" aria-hidden="true"></i>{{ $label }}
        </span>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 id="judul-lembar" class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">{{ $judul }}</h2>
                @if ($keterangan)
                    <p class="mt-2 text-gray-600">{{ $keterangan }}</p>
                @endif
            </div>
            {{ $kanan ?? '' }}
        </div>
        {{ $slot }}
    </div>
</section>

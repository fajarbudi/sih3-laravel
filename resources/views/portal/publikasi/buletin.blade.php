{{--
    Publikasi > Buletin (PublikasiController@buletin), pengganti info/buletin lama.
    SUSUNAN MENGIKUTI PORTAL LIVE LAMA (pilihan user): satu kartu per kategori, bagian atas berlatar warna khas kategori
    dengan sampul utuh di tengah, lalu titik warna + nama kategori, tanggal, judul, "Lihat semua edisi" berwarna kategori.
    Penyegaran: kartu rounded-lg (radius dikurangi atas permintaan user), jumlah edisi asli di pojok sampul. Revisi user ("sampul kecil, kartu terlalu besar"):
    sampul MEMENUHI bagian atas kartu (object-cover, dipotong dari atas), kartu dipadatkan. Latar warna kategori hanya
    terlihat bila kategori belum punya gambar. Kategori tanpa edisi tetap tampil (seperti lama) dengan ikon kategori.
--}}
@php
    $kategori = App\Http\Controllers\PublikasiController::BULETIN;
    $warna = App\Http\Controllers\PublikasiController::WARNA_BULETIN;
    $angka = fn ($n) => number_format($n, 0, ',', '.');
@endphp

<x-layouts.portal judul="Buletin" :hero-gelap="true" deskripsi="Infografis buletin hidrologi, hidrometeorologi, dan hidrogeologi Wilayah Sungai Serayu Opak.">
    <x-portal.hero label="Publikasi" judul="Buletin">Infografis buletin hidrologi, hidrometeorologi, dan hidrogeologi Wilayah Sungai Serayu Opak.</x-portal.hero>

    <x-portal.lembar label="Buletin" ikon="bi-newspaper" judul="Edisi terbaru" keterangan="Edisi terbaru dari setiap kategori buletin. Klik kartu untuk melihat seluruh edisi.">
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
            @foreach ($kategori as $slug => [, $judul, $keterangan, $ikon])
                @php
                    $b = $terbaru[$slug];
                    $sampul = $b?->lampiran->where('jenis', 'gambar')->first(fn ($l) => Storage::disk('public')->exists($l->path));
                    [$latar, $titik, $teks] = $warna[$slug];
                @endphp
                <a href="{{ route('portal.buletin.kategori', $slug) }}"
                    class="group flex flex-col overflow-hidden rounded-lg bg-white ring-1 ring-gray-200 transition duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-gray-950/10 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
                    {{-- Sampul utuh (tidak dipotong) di atas latar warna kategori, seperti lama. --}}
                    <span class="relative flex aspect-[16/10] items-center justify-center overflow-hidden {{ $latar }}">
                        @if ($sampul)
                            <img src="{{ $sampul->urlKecil() }}" alt="" width="480" height="300" loading="lazy"
                                class="size-full object-cover object-top transition duration-300 group-hover:scale-[1.03] motion-reduce:transition-none">
                        @else
                            <span class="flex flex-col items-center gap-3 text-sm font-semibold {{ $teks }}">
                                <span class="flex size-14 items-center justify-center rounded-full bg-white text-2xl shadow-sm"><i class="bi {{ $ikon }}" aria-hidden="true"></i></span>
                                Belum ada gambar
                            </span>
                        @endif
                        @if ($jumlah[$slug])
                            <span class="absolute right-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-xs font-bold tabular-nums text-gray-800 shadow-sm">{{ $angka($jumlah[$slug]) }} edisi</span>
                        @endif
                    </span>
                    <span class="flex flex-1 flex-col p-4 sm:p-5">
                        <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider {{ $teks }}">
                            <span class="size-2.5 rounded-full {{ $titik }}" aria-hidden="true"></span>{{ $judul }}
                        </span>
                        @if ($b)
                            <span class="mt-1.5 line-clamp-2 font-bold leading-snug text-gray-950">{{ $b->judul }}</span>
                            <span class="mt-1 text-sm text-gray-600">{{ $b->tanggal?->translatedFormat('j F Y') }}</span>
                        @else
                            <span class="mt-1.5 font-bold text-gray-500">Belum ada edisi</span>
                            <span class="mt-1 line-clamp-2 text-sm text-gray-600">{{ $keterangan }}</span>
                        @endif
                        <span class="mt-auto inline-flex items-center gap-1 pt-3 text-sm font-bold {{ $teks }}">
                            Lihat semua edisi<i class="bi bi-chevron-right text-xs transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" aria-hidden="true"></i>
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    </x-portal.lembar>
</x-layouts.portal>

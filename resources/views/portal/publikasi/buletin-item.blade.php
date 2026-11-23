{{--
    Satu edisi buletin di halaman kategori, mengikuti kartu portal live lama. Revisi user ("sampul kecil, kartu terlalu
    besar"): infografis MEMENUHI bagian atas kartu (object-cover dari atas), kartu padat; latar warna kategori hanya untuk
    edisi tanpa gambar,
    label (nama produk bila ada, selain itu nama kategori) berwarna kategori, judul, tanggal + Unduh.
    Klik gambar/judul membuka jendela infografis ukuran penuh (buletin-jendela).
    Variabel: $b, $label, $latar & $teks (warna kategori dari PublikasiController::WARNA_BULETIN).
--}}
@php
    $sampul = $b->lampiran->where('jenis', 'gambar')->first(fn ($l) => Storage::disk('public')->exists($l->path));
@endphp
<article class="group flex flex-col overflow-hidden rounded-lg bg-white ring-1 ring-gray-200 transition duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-gray-950/10 motion-reduce:transition-none motion-reduce:hover:translate-y-0">
    <button type="button" data-jendela="buletin-{{ $b->id }}" data-judul="{{ $b->judul }}" class="relative flex aspect-[4/3] items-center justify-center overflow-hidden {{ $latar }}">
        @if ($sampul)
            <img src="{{ $sampul->urlKecil() }}" alt="" width="400" height="300" loading="lazy"
                class="size-full object-cover object-top transition duration-300 group-hover:scale-[1.03] motion-reduce:transition-none">
            <span class="absolute right-2.5 top-2.5 flex size-9 items-center justify-center rounded-full bg-white/95 text-gray-800 opacity-90 shadow-sm transition group-hover:opacity-100" aria-hidden="true">
                <i class="bi bi-arrows-angle-expand"></i>
            </span>
            <span class="sr-only">Perbesar {{ $b->judul }}</span>
        @else
            <span class="flex flex-col items-center gap-2 text-sm font-semibold {{ $teks }}"><i class="bi bi-image text-3xl" aria-hidden="true"></i>Belum ada gambar<span class="sr-only">, lihat detail {{ $b->judul }}</span></span>
        @endif
    </button>
    <div class="flex flex-1 flex-col p-3 sm:p-4">
        <p class="line-clamp-1 text-[11px] font-bold uppercase tracking-wider {{ $teks }}">{{ $b->kategori?->nama ?? $label }}</p>
        <h4 class="mt-1 line-clamp-2 text-sm font-bold leading-snug text-gray-950 sm:text-base" title="{{ $b->judul }}">{{ $b->judul }}</h4>
        <div class="mt-auto flex flex-wrap items-center justify-between gap-x-3 pt-2 text-[13px] text-gray-600">
            <span class="inline-flex items-center gap-1.5"><i class="bi bi-calendar3" aria-hidden="true"></i><time datetime="{{ $b->tanggal?->toDateString() }}">{{ $b->tanggal?->translatedFormat('j F Y') }}</time></span>
            @if ($sampul)
                <a href="{{ $sampul->url() }}" download class="-mx-2 inline-flex min-h-10 items-center gap-1.5 rounded-full px-2 font-bold text-gray-800 hover:bg-gray-100 hover:text-gray-950">
                    <i class="bi bi-download" aria-hidden="true"></i>Unduh<span class="sr-only"> {{ $b->judul }}</span>
                </a>
            @endif
        </div>
    </div>
    @include('portal.publikasi.buletin-jendela')
</article>

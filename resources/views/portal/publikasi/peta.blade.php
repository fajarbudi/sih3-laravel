{{--
    Publikasi > Peta (PublikasiController@peta), pengganti info/galeripeta lama: pilihan jenis peta + cari judul, galeri
    tanpa bingkai kartu (permintaan user): gambar tanpa sudut bulat lalu jenis, judul, keterangan, tanggal, Unduh PDF; 9 per halaman. Klik peta membuka jendela gambar besar dengan
    tombol unduh. Keterangan kosong diganti keterangan per jenis seperti lama.
--}}
@php
    $chip = 'inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold';
    $chipBiasa = "$chip bg-gray-100 text-gray-800 hover:bg-gray-200/70";
    $chipAktif = "$chip bg-primary-600 text-white shadow-md shadow-primary-600/25";
    $keteranganJenis = [
        'peta-hidrologi' => 'Peta kondisi hidrologi dan potensi kekeringan di Wilayah Sungai Serayu Opak.',
        'peta-analisa-curah-hujan' => 'Peta analisa curah hujan dan indeks presipitasi di Wilayah Sungai Serayu Opak.',
        'peta-cekungan-air-tanah' => 'Peta sebaran cekungan air tanah (CAT) di wilayah kerja BBWS Serayu Opak.',
    ];
    $cari = request('q');
@endphp

<x-layouts.portal judul="Galeri Peta" :hero-gelap="true" deskripsi="Peta hidrologi, analisa curah hujan, dan cekungan air tanah Wilayah Sungai Serayu Opak.">
    <x-portal.hero label="Publikasi" judul="Galeri Peta">Peta hidrologi, analisa curah hujan, dan cekungan air tanah Wilayah Sungai Serayu Opak.</x-portal.hero>

    <x-portal.lembar label="Galeri peta" ikon="bi-map" judul="Koleksi peta" keterangan="Klik peta untuk melihat ukuran penuh dan mengunduhnya.">
        <div class="mt-6 flex flex-wrap items-center gap-2">
            <nav aria-label="Jenis peta" class="flex flex-wrap gap-2">
                <a href="{{ route('portal.peta', array_filter(['q' => $cari])) }}" @if (! $aktif) aria-current="page" @endif class="{{ $aktif ? $chipBiasa : $chipAktif }}">Semua</a>
                @foreach ($kategori as $k)
                    <a href="{{ route('portal.peta', array_filter(['jenis' => $k->slug, 'q' => $cari])) }}" @if ($aktif?->is($k)) aria-current="page" @endif class="{{ $aktif?->is($k) ? $chipAktif : $chipBiasa }}">{{ $k->nama }}</a>
                @endforeach
            </nav>
            <form method="get" role="search" class="flex h-11 w-full items-center gap-2 rounded-full bg-gray-100 pl-4 pr-1 ring-1 ring-inset ring-transparent focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500 sm:ml-auto sm:w-80">
                @if ($aktif) <input type="hidden" name="jenis" value="{{ $aktif->slug }}"> @endif
                <i class="bi bi-search text-gray-600" aria-hidden="true"></i>
                <label for="cari-peta" class="sr-only">Cari peta</label>
                <input type="search" id="cari-peta" name="q" value="{{ $cari }}" placeholder="Cari peta…" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0">
                <button class="min-h-9 rounded-full bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700">Cari</button>
            </form>
        </div>

        @if ($cari)
            <p class="mt-4 text-sm text-gray-700">Hasil pencarian untuk “<strong class="text-gray-950">{{ $cari }}</strong>” ·
                <a href="{{ route('portal.peta', array_filter(['jenis' => $aktif?->slug])) }}" class="font-semibold text-primary-700 underline-offset-2 hover:underline">Hapus pencarian</a></p>
        @endif

        @if ($peta->isEmpty())
            <p class="mt-8 flex flex-col items-center gap-3 border-y border-gray-200 py-16 text-center text-gray-600">
                <i class="bi bi-map text-3xl text-gray-400" aria-hidden="true"></i>
                {{ $cari ? 'Tidak ada peta yang cocok dengan pencarian.' : 'Belum ada peta untuk jenis ini.' }}
            </p>
        @else
            <div class="mt-8 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($peta as $p)
                    @php
                        $gambar = $p->lampiran->firstWhere('jenis', 'gambar');
                        $pdf = $p->lampiran->firstWhere('jenis', 'pdf');
                        $keterangan = trim(strip_tags($p->keterangan ?? '')) ?: ($keteranganJenis[$p->kategori?->slug] ?? null);
                    @endphp
                    <article class="group flex flex-col">
                        <button type="button" data-jendela="peta-{{ $p->id }}" data-judul="{{ $p->nama }}" class="relative block aspect-[4/3] overflow-hidden bg-gray-100 ring-1 ring-gray-950/5">
                            @if ($gambar)
                                <img src="{{ $gambar->urlKecil() }}" alt="" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-[1.03] motion-reduce:transition-none">
                            @endif
                            <span class="absolute bottom-3 right-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-bold text-gray-900 shadow-sm">
                                <i class="bi bi-zoom-in" aria-hidden="true"></i>Perbesar<span class="sr-only"> {{ $p->nama }}</span>
                            </span>
                        </button>
                        <div class="flex flex-1 flex-col pt-4">
                            @if ($p->kategori)
                                <p class="text-xs font-bold uppercase tracking-wider text-primary-600">{{ $p->kategori->nama }}</p>
                            @endif
                            <h3 class="mt-1 font-bold leading-snug text-gray-950 group-hover:text-primary-700">{{ $p->nama }}</h3>
                            @if ($keterangan)
                                <p class="mt-2 line-clamp-2 text-sm text-gray-600">{{ $keterangan }}</p>
                            @endif
                            <div class="mt-auto flex items-center justify-between gap-3 pt-3 text-sm text-gray-600">
                                <span class="inline-flex items-center gap-1.5"><i class="bi bi-calendar3" aria-hidden="true"></i>{{ $p->tanggal?->translatedFormat('j F Y') }}</span>
                                @if ($pdf)
                                    <a href="{{ $pdf->url() }}" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-red-50 px-3 font-bold text-red-700 hover:bg-red-100">
                                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>Unduh PDF<span class="sr-only"> {{ $p->nama }}</span>
                                    </a>
                                @endif
                            </div>
                        </div>
                        <template id="peta-{{ $p->id }}">
                            @if ($gambar)
                                <a href="{{ $gambar->url() }}" target="_blank" rel="noopener" class="block overflow-hidden bg-gray-100 ring-1 ring-gray-200">
                                    <img src="{{ $gambar->url() }}" alt="{{ $p->nama }}" class="mx-auto max-h-[65vh] w-auto">
                                </a>
                            @endif
                            <p class="mt-4 text-sm text-gray-600">{{ $p->tanggal?->translatedFormat('l, j F Y') }}</p>
                            @if ($keterangan) <p class="mt-1 text-gray-800">{{ $keterangan }}</p> @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($gambar)
                                    <a href="{{ $gambar->url() }}" download class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700"><i class="bi bi-download" aria-hidden="true"></i>Unduh gambar</a>
                                @endif
                                @if ($pdf)
                                    <a href="{{ $pdf->url() }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-red-50 px-4 text-sm font-bold text-red-700 hover:bg-red-100"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>Unduh PDF</a>
                                @endif
                                @if ($p->url)
                                    <a href="{{ $p->url }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-gray-100 px-4 text-sm font-bold text-gray-800 hover:bg-gray-200">Kunjungi halaman<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                                @endif
                            </div>
                        </template>
                    </article>
                @endforeach
            </div>
            <x-portal.pagination :data="$peta" satuan="peta" />
        @endif
    </x-portal.lembar>

    <x-portal.jendela lebar="w-[min(72rem,calc(100%-2rem))]" />
</x-layouts.portal>

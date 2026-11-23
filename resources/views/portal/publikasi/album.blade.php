{{--
    Publikasi > Album Kegiatan (PublikasiController@album), pengganti info/album lama (valbum): pilihan jenis
    (Acara/Lapangan), susunan lama per halaman: album terbaru besar + satu kartu samping, tiga kartu, lalu
    "Dokumentasi lainnya" berupa baris foto-teks bergantian kiri/kanan. Klik album membuka jendela berisi semua foto
    (miniatur mengganti foto utama), keterangan, video YouTube, dan tautan file PDF (pengganti portal/detilalbum lama).
    Revisi 2026-11-24 (selaras Buletin/peta): radius kartu rounded-lg; album utama = foto + panel biru tua DI BAWAH foto
    seperti live (judul tidak lagi menimpa teks yang tercetak di foto); "Dokumentasi lainnya" tanpa kotak seperti live;
    pagination & pesan kosong gaya portal.
--}}
@php
    $chip = 'inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold';
    $chipBiasa = "$chip bg-gray-100 text-gray-800 hover:bg-gray-200/70";
    $chipAktif = "$chip bg-primary-600 text-white shadow-md shadow-primary-600/25";
    $daftar = $album->getCollection()->map(function ($a) {
        $foto = $a->lampiran->where('jenis', 'gambar')->values();
        // ID video YouTube dari tautan watch?v=..., youtu.be/..., atau ID saja (seperti lama).
        $video = trim($a->url_video ?? '');
        if (preg_match('~(?:[?&]v=|youtu\.be/|/embed/)([\w-]{6,})~', $video, $m)) {
            $video = $m[1];
        }

        return (object) [
            'album' => $a,
            'foto' => $foto,
            'sampul' => $foto->first(),
            'video' => preg_match('/^[\w-]{6,}$/', $video) ? $video : null,
            'keterangan' => trim(html_entity_decode(strip_tags($a->keterangan ?? ''), ENT_QUOTES)) ?: 'Dokumentasi kegiatan Tim Hidrologi & Kualitas Air BBWS Serayu Opak.',
            'tanggal' => $a->tanggal?->translatedFormat('j F Y'),
        ];
    });
    [$utama, $samping, $tiga, $baris] = [$daftar->slice(0, 1), $daftar->slice(1, 1), $daftar->slice(2, 3), $daftar->slice(5)->values()];
    $kicker = 'text-xs font-bold uppercase tracking-wider';
@endphp

<x-layouts.portal judul="Album Kegiatan" :hero-gelap="true" deskripsi="Dokumentasi kegiatan acara dan lapangan pengelolaan hidrologi Wilayah Sungai Serayu Opak.">
    <x-portal.hero label="Publikasi" judul="Album Kegiatan">Dokumentasi kegiatan acara dan lapangan pengelolaan hidrologi Wilayah Sungai Serayu Opak.</x-portal.hero>

    <x-portal.lembar label="Album" ikon="bi-camera" judul="Galeri kegiatan" keterangan="Klik album untuk melihat seluruh foto dokumentasi kegiatan.">
        <nav aria-label="Jenis album" class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('portal.album') }}" @if (! $jenis) aria-current="page" @endif class="{{ $jenis ? $chipBiasa : $chipAktif }}">Semua</a>
            @foreach (['Acara', 'Lapangan'] as $j)
                <a href="{{ route('portal.album', ['jenis' => $j]) }}" @if ($jenis === $j) aria-current="page" @endif class="{{ $jenis === $j ? $chipAktif : $chipBiasa }}">{{ $j }}</a>
            @endforeach
        </nav>

        @if ($daftar->isEmpty())
            <p class="mt-8 flex flex-col items-center gap-3 border-y border-gray-200 py-16 text-center text-gray-600">
                <i class="bi bi-camera text-3xl text-gray-400" aria-hidden="true"></i>Belum ada album untuk jenis ini.
            </p>
        @else
            {{-- Baris 1: album terbaru besar + kartu samping --}}
            <div class="mt-8 grid gap-5 lg:grid-cols-3">
                @foreach ($utama as $d)
                    <button type="button" data-jendela="album-{{ $d->album->id }}" data-judul="{{ $d->album->nama }}"
                        class="group flex flex-col overflow-hidden rounded-lg bg-primary-950 text-left text-white lg:col-span-2">
                        <span class="block aspect-[16/9] w-full overflow-hidden bg-primary-900">
                            @if ($d->sampul)
                                <img src="{{ $d->sampul->url() }}" alt="" width="960" height="540" class="size-full object-cover transition duration-500 group-hover:scale-[1.02] motion-reduce:transition-none">
                            @endif
                        </span>
                        <span class="block p-5 sm:px-7 sm:py-6">
                            <span class="{{ $kicker }} text-amber-300">{{ $d->album->jenis }}</span>
                            <span class="mt-1.5 block text-xl font-extrabold leading-tight tracking-tight text-balance group-hover:underline group-hover:decoration-2 group-hover:underline-offset-4 sm:text-2xl">{{ $d->album->nama }}</span>
                            <span class="mt-1.5 block text-sm text-white/80">{{ $d->tanggal }}</span>
                        </span>
                    </button>
                @endforeach
                @foreach ($samping as $d)
                    @include('portal.publikasi.album-kartu', ['ringkas' => false])
                @endforeach
            </div>

            {{-- Baris 2: tiga kartu --}}
            @if ($tiga->isNotEmpty())
                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($tiga as $d)
                        @include('portal.publikasi.album-kartu', ['ringkas' => true])
                    @endforeach
                </div>
            @endif

            {{-- Dokumentasi lainnya: foto & teks bergantian kiri/kanan --}}
            @if ($baris->isNotEmpty())
                <h3 class="mt-14 border-t border-gray-200 pt-10 text-2xl font-extrabold tracking-tight text-gray-950">Dokumentasi lainnya</h3>
                <div class="mt-8 space-y-10">
                    @foreach ($baris as $d)
                        <button type="button" data-jendela="album-{{ $d->album->id }}" data-judul="{{ $d->album->nama }}"
                            class="group grid w-full items-center gap-4 text-left sm:grid-cols-2 sm:gap-10">
                            <span class="block aspect-[16/10] overflow-hidden rounded-lg bg-gray-100 {{ $loop->odd ? '' : 'sm:order-2' }}">
                                @if ($d->sampul) <img src="{{ $d->sampul->urlKecil() }}" alt="" width="640" height="400" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-[1.03] motion-reduce:transition-none"> @endif
                            </span>
                            <span class="block">
                                <span class="{{ $kicker }} text-gray-500">{{ $d->album->jenis }} · {{ $d->tanggal }}</span>
                                <span class="mt-2 block text-xl font-extrabold leading-snug tracking-tight text-gray-950 group-hover:text-primary-700">{{ $d->album->nama }}</span>
                                <span class="mt-2 line-clamp-3 text-gray-600">{{ $d->keterangan }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif

            <x-portal.pagination :data="$album" satuan="album" />

            {{-- Isi jendela tiap album --}}
            @foreach ($daftar as $d)
                <template id="album-{{ $d->album->id }}">
                    @if ($d->sampul)
                        <div class="overflow-hidden rounded-lg bg-gray-950">
                            <img data-foto-utama src="{{ $d->sampul->url() }}" alt="{{ $d->album->nama }}" class="mx-auto max-h-[60vh] w-auto">
                        </div>
                        @if ($d->foto->count() > 1)
                            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                                @foreach ($d->foto as $f)
                                    <button type="button" data-foto="{{ $f->url() }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"
                                        class="size-16 shrink-0 overflow-hidden rounded-md opacity-70 ring-2 ring-transparent hover:opacity-100 aria-[current=true]:opacity-100 aria-[current=true]:ring-primary-500 sm:size-20">
                                        <img src="{{ $f->urlKecil() }}" alt="Foto {{ $loop->iteration }}" loading="lazy" class="size-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endif
                    <p class="mt-4 text-sm text-gray-600">{{ $d->album->jenis }} · {{ $d->album->tanggal?->translatedFormat('l, j F Y') }}</p>
                    <p class="mt-1 whitespace-pre-line text-gray-800">{{ $d->keterangan }}</p>
                    @if ($d->album->url)
                        <p class="mt-4">
                            <a href="{{ $d->album->url }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-red-50 px-4 text-sm font-bold text-red-700 hover:bg-red-100">
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>Unduh file PDF<span class="sr-only"> (tab baru)</span>
                            </a>
                        </p>
                    @endif
                    @if ($d->video)
                        <iframe src="https://www.youtube-nocookie.com/embed/{{ $d->video }}" title="Video {{ $d->album->nama }}" loading="lazy"
                            allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen class="mt-5 aspect-video w-full rounded-lg bg-gray-950"></iframe>
                    @endif
                </template>
            @endforeach
        @endif
    </x-portal.lembar>

    <x-portal.jendela />
</x-layouts.portal>

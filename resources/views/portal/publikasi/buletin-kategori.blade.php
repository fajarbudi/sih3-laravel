{{--
    Edisi satu kategori buletin (PublikasiController@buletinKategori), pengganti info/buletin/{jenis} lama (vbuletin):
    susunan mengikuti portal live lama: pil kategori bertitik warna, edisi dikelompokkan per sumber (judul sumber +
    jumlah edisi), kisi 4 kolom kartu padat. Lama menampilkan seluruh edisi sekaligus (ratusan gambar);
    kini tiap sumber 8 edisi terbaru + "Lihat semua", yang membuka semua edisi sumber itu per 24.
--}}
@php
    $kategori = App\Http\Controllers\PublikasiController::BULETIN;
    [, $label, $keterangan] = $kategori[$slug];
    $warna = App\Http\Controllers\PublikasiController::WARNA_BULETIN;
    [$latar, , $teks] = $warna[$slug];
    $chip = 'inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold';
    $chipBiasa = "$chip bg-gray-100 text-gray-800 hover:bg-gray-200/70";
    $chipAktif = "$chip bg-primary-600 text-white shadow-md shadow-primary-600/25";
    $kisi = 'mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4';
@endphp

<x-layouts.portal :judul="'Buletin '.$label" :hero-gelap="true" :deskripsi="$keterangan">
    <x-portal.hero label="Buletin" :judul="$label">{{ $keterangan }}</x-portal.hero>

    <x-portal.lembar label="Buletin" ikon="bi-newspaper" :judul="$sumber ? $sumber : 'Edisi '.Str::lower($label)" keterangan="Klik infografis untuk melihat ukuran penuh dan mengunduhnya.">
        <nav aria-label="Kategori buletin" class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('portal.buletin') }}" class="{{ $chipBiasa }}"><i class="bi bi-grid" aria-hidden="true"></i>Semua</a>
            @foreach ($kategori as $s => [, $judul])
                <a href="{{ route('portal.buletin.kategori', $s) }}" @if ($s === $slug) aria-current="page" @endif class="{{ $s === $slug ? $chipAktif : $chipBiasa }}">
                    <span class="size-2 rounded-full {{ $s === $slug ? 'bg-white' : $warna[$s][1] }}" aria-hidden="true"></span>{{ $judul }} <span class="tabular-nums opacity-80">{{ number_format($jumlahKategori[$s], 0, ',', '.') }}</span>
                </a>
            @endforeach
        </nav>

        @if ($halaman)
            {{-- Semua edisi satu sumber --}}
            <p class="mt-6">
                <a href="{{ route('portal.buletin.kategori', $slug) }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-primary-700 hover:bg-primary-50">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>Kembali ke semua sumber
                </a>
            </p>
            <div class="{{ $kisi }}">
                @foreach ($halaman as $b)
                    @include('portal.publikasi.buletin-item')
                @endforeach
            </div>
            <x-portal.pagination :data="$halaman" satuan="edisi" />
        @elseif ($kelompok->isEmpty())
            <p class="mt-8 flex flex-col items-center gap-3 border-y border-gray-200 py-16 text-center text-gray-600">
                <i class="bi bi-newspaper text-3xl text-gray-400" aria-hidden="true"></i>Belum ada edisi buletin {{ Str::lower($label) }}.
            </p>
        @else
            @foreach ($kelompok as $namaSumber => $daftar)
                <section aria-labelledby="sumber-{{ $loop->iteration }}" class="mt-12 border-t border-gray-200 pt-8 first-of-type:mt-8">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h3 id="sumber-{{ $loop->iteration }}" class="text-xl font-extrabold tracking-tight text-gray-950 sm:text-2xl">{{ $namaSumber }}</h3>
                            <p class="mt-1 text-sm tabular-nums text-gray-600">{{ number_format($jumlah[$namaSumber], 0, ',', '.') }} edisi</p>
                        </div>
                        @if ($jumlah[$namaSumber] > $daftar->count())
                            <a href="{{ route('portal.buletin.kategori', [$slug, 'sumber' => $namaSumber]) }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-gray-100 px-4 text-sm font-bold text-gray-800 hover:bg-gray-200">
                                Lihat semua<span class="sr-only"> edisi {{ $namaSumber }}</span><i class="bi bi-chevron-right text-xs" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                    <div class="{{ $kisi }}">
                        @foreach ($daftar as $b)
                            @include('portal.publikasi.buletin-item')
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </x-portal.lembar>

    <x-portal.jendela />
</x-layouts.portal>

{{--
    Beranda portal publik. Isi DAN susunan mengikuti beranda live lama (sih3.bbwsserayuopak.id), yang dinilai user lebih
    bagus daripada versi-versi baru: hero foto dengan judul di tengah, peta dengan baris filter + legenda ikon,
    tab tiga bidang horizontal, deretan logo walidata bernama. Perbedaan yang diminta user: peta selebar layar.
    Aset dari aplikasi lama: ikon penanda per jenis/status (public/images/portal/penanda), gambar bidang
    (public/images/portal/bidang), batas DAS 2025 yang dirampingkan (public/geojson/das.json).
--}}
@php
    $kelompok = App\Http\Controllers\PortalController::KELOMPOK;
    $angka = fn ($n) => number_format($n, 0, ',', '.');
    $ikon = fn ($nama) => asset("images/portal/penanda/$nama.png");
    // Nama berkas ikon per kelompok (sama dengan aplikasi lama).
    $berkasIkon = ['awlr' => 'dugaair', 'kualitas_air' => 'kualitasair', 'arr' => 'curahhujan', 'klimatologi' => 'klimatologi', 'mat' => 'mat'];
    // Legenda status = legenda aplikasi lama (indeks = kelas di PortalController::KELAS).
    $legenda = [
        ['Curah hujan', 'curahhujan', [1 => ['Tidak hujan', '0 mm'], ['Hujan sangat ringan', '0,1 sampai 1 mm'], ['Hujan ringan', '1 sampai 5 mm'],
            ['Hujan sedang', '5 sampai 10 mm'], ['Hujan lebat', '10 sampai 20 mm'], ['Hujan sangat lebat', '20 mm atau lebih']]],
        ['Duga air', 'dugaair', [1 => ['Normal', ''], ['Waspada', ''], ['Siaga', ''], ['Awas', '']]],
        ['Kualitas air', 'kualitasair', [1 => ['Baik', 'PIj 1,0 atau kurang'], ['Cemar ringan', '1,0 sampai 5,0'], ['Cemar sedang', '5,0 sampai 10'], ['Cemar berat', 'lebih dari 10']]],
    ];
    $label = 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide';
    $tombol = 'inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 text-sm font-medium text-gray-800 hover:border-gray-300 hover:bg-gray-50';
@endphp

<x-layouts.portal :hero-gelap="true">
    {{-- 1. HERO: foto bendung selebar layar di bawah header transparan, judul di tengah. --}}
    <section class="relative isolate -mt-16 overflow-hidden bg-primary-950 pt-16 text-center text-white lg:-mt-[4.5rem] lg:pt-[4.5rem]">
        <img src="{{ asset('images/portal/hero-1920.jpg') }}" srcset="{{ asset('images/portal/hero-960.jpg') }} 960w, {{ asset('images/portal/hero-1920.jpg') }} 1920w"
            sizes="100vw" alt="" width="1920" height="1103" fetchpriority="high" class="absolute inset-0 -z-20 size-full object-cover">
        <div class="absolute inset-0 -z-10 bg-primary-950/70" aria-hidden="true"></div>
        <div class="mx-auto max-w-4xl px-4 pb-20 pt-14 sm:pb-24 sm:pt-20">
            <h1>
                <span class="block text-sm font-bold uppercase tracking-[0.2em] text-amber-300">Portal SIH3</span>
                <span class="mt-4 block text-[1.75rem] font-extrabold leading-tight tracking-tight text-balance sm:text-5xl">Wilayah Sungai <span class="whitespace-nowrap">Serayu-Bogowonto</span> &amp; <span class="whitespace-nowrap">Progo-Opak-Serang</span></span>
            </h1>
            <span class="mx-auto mt-6 block h-1 w-14 rounded-full bg-amber-300" aria-hidden="true"></span>
            <p class="mx-auto mt-6 max-w-xl text-lg text-white/90">Portal publikasi informasi pengelolaan hidrologi, hidrometeorologi, dan hidrogeologi.</p>
        </div>
    </section>

    {{-- 2. PETA: judul + jumlah titik, baris filter, peta selebar layar, legenda ikon. --}}
    <section id="peta" aria-labelledby="judul-peta" class="scroll-mt-16 bg-white py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <span class="{{ $label }} bg-primary-50 text-primary-700"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>Peta sebaran</span>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="judul-peta" class="text-3xl font-extrabold tracking-tight text-gray-950">Peta pos pemantauan</h2>
                    <p class="mt-2 max-w-2xl text-gray-600">Sebaran pos hidrologi, hidrometeorologi, dan hidrogeologi. Klik pos untuk melihat detail data.</p>
                </div>
                <p aria-live="polite" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3.5 py-2 text-sm text-gray-700">
                    <span class="size-2 rounded-full bg-green-500" aria-hidden="true"></span>Menampilkan <strong id="jumlah-titik" class="font-bold tabular-nums text-gray-950">{{ $angka(count($titik)) }}</strong> titik pos
                </p>
            </div>

            {{-- Baris filter seperti aplikasi lama. Dropdown jenis per bidang memakai <details> (data-dropdown: tertutup saat klik di luar/Escape). --}}
            <div class="mt-6 flex flex-wrap items-center gap-2">
                <div class="inline-flex gap-2" role="group" aria-label="Mode tampilan peta">
                    <button type="button" data-mode="semua" aria-pressed="true" class="{{ $tombol }} aria-pressed:border-primary-500 aria-pressed:bg-primary-500 aria-pressed:text-white">Tampilkan semua</button>
                    <button type="button" data-mode="peringatan" aria-pressed="false" class="{{ $tombol }} aria-pressed:border-primary-500 aria-pressed:bg-primary-500 aria-pressed:text-white">Pos peringatan <span class="tabular-nums">({{ $angka($jumlahPeringatan) }})</span></button>
                </div>
                <span class="mx-1 hidden h-6 w-px bg-gray-200 sm:block" aria-hidden="true"></span>
                @foreach ($bidang as $kunciBidang => $b)
                    <details class="relative" data-dropdown>
                        <summary class="{{ $tombol }} list-none [&::-webkit-details-marker]:hidden">{{ $b['label'] }} <i class="bi bi-chevron-down text-xs" aria-hidden="true"></i></summary>
                        <fieldset class="absolute left-0 top-full z-[1100] mt-2 w-64 rounded-xl border border-gray-100 bg-white p-2 shadow-xl shadow-gray-950/10">
                            <legend class="sr-only">Jenis pos {{ $b['label'] }}</legend>
                            @foreach ($kelompok as $kel => [$labelJenis, $tipe])
                                @continue($tipe !== $kunciBidang)
                                <label class="flex min-h-11 items-center gap-2.5 rounded-lg px-2 text-sm hover:bg-gray-50">
                                    <input type="checkbox" checked data-filter-kel="{{ $kel }}" class="size-4 accent-primary-500">
                                    <img src="{{ $ikon($berkasIkon[$kel]) }}" alt="" width="20" height="23" class="h-[23px] w-5">
                                    <span class="flex-1 text-gray-800">{{ $labelJenis }}</span>
                                    <span class="tabular-nums text-gray-600">{{ $angka($jumlahPerKelompok[$kel] ?? 0) }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    </details>
                @endforeach
                <label for="filter-walidata" class="sr-only">Walidata</label>
                <select id="filter-walidata" class="{{ $tombol }} max-w-56 pr-8">
                    <option value="">Semua walidata</option>
                    @foreach ($walidata as $w)
                        <option value="{{ $w->id }}">{{ $w->nama }}</option>
                    @endforeach
                </select>
                <span class="mx-1 hidden h-6 w-px bg-gray-200 sm:block" aria-hidden="true"></span>
                <button type="button" data-lapisan="das" aria-pressed="true" class="{{ $tombol }} aria-pressed:border-primary-200 aria-pressed:bg-primary-50 aria-pressed:text-primary-700">
                    <i class="bi bi-bounding-box" aria-hidden="true"></i> Batas DAS
                </button>
            </div>
        </div>

        {{-- Peta selebar layar (permintaan user). Tinggi tetap agar tata letak tidak bergeser; isolate menahan z-index Leaflet. --}}
        <div class="relative isolate mt-6 h-[480px] border-y border-gray-200 bg-gray-100 sm:h-[600px] lg:h-[680px]">
            <p id="peta-memuat" class="absolute inset-0 flex items-center justify-center text-gray-600">Memuat peta…</p>
            <div id="peta-leaflet" class="absolute inset-0" role="region" aria-label="Peta pos pemantauan"
                data-ikon="{{ asset('images/portal/penanda') }}" data-das="{{ asset('geojson/das.json') }}"></div>
            <p id="peta-kosong" hidden class="absolute inset-x-4 top-4 z-[500] mx-auto max-w-sm rounded-xl bg-white p-4 text-center text-sm text-gray-700 shadow-lg">Tidak ada pos yang cocok dengan filter. Ubah pilihan jenis atau walidata.</p>
            <p id="peta-galat" hidden class="absolute inset-x-4 bottom-12 z-[500] mx-auto max-w-sm rounded-xl bg-white p-4 text-center text-sm text-gray-700 shadow-lg">Sebagian ubin peta OpenStreetMap gagal dimuat. Pos tetap tampil.</p>
            <noscript><p class="absolute inset-0 flex items-center justify-center p-6 text-center text-gray-700">Peta membutuhkan JavaScript.</p></noscript>

            {{-- Legenda MENDATAR di tengah bawah peta (permintaan user): bagian bawah peta adalah laut, jadi tidak menutupi pos.
                 Lebar menyisakan ruang tombol zoom di kanan. Isi mengikuti mode (data-legenda, diatur peta.js).
                 Terbuka di layar lebar, tertutup di ponsel (skrip di bawah). --}}
            <details open id="legenda" class="group absolute bottom-4 left-1/2 z-[500] max-h-[calc(100%-2rem)] w-[min(60rem,calc(100%-7rem))] -translate-x-1/2 overflow-y-auto rounded-2xl bg-white/95 text-sm shadow-xl shadow-gray-950/15 backdrop-blur">
                <summary class="sticky top-0 flex min-h-11 list-none items-center justify-center gap-2 bg-white/95 px-4 font-bold text-gray-950 [&::-webkit-details-marker]:hidden">
                    <i class="bi bi-list-ul" aria-hidden="true"></i> Legenda
                    <i class="bi bi-chevron-up text-xs text-gray-600 group-open:rotate-180" aria-hidden="true"></i>
                </summary>
                <div class="border-t border-gray-100 px-4 pb-4 pt-3 sm:px-6">
                    <div data-legenda="semua" class="text-center">
                        <ul class="flex flex-wrap justify-center gap-x-6 gap-y-2">
                            @foreach ($kelompok as $kel => [$labelJenis])
                                <li class="flex items-center gap-2"><img src="{{ $ikon($berkasIkon[$kel]) }}" alt="" width="20" height="23" class="h-[23px] w-5 shrink-0"> <span class="text-gray-800">{{ $labelJenis }}</span></li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-gray-600">Warna ikon menunjukkan status terkini. Pilih <strong class="font-semibold text-gray-800">Pos peringatan</strong> untuk arti setiap warna.</p>
                    </div>
                    <div data-legenda="peringatan" hidden class="grid gap-x-8 gap-y-4 sm:grid-cols-3">
                        @foreach ($legenda as [$judul, $berkas, $kelas])
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-primary-700">{{ $judul }}</p>
                                <ul class="mt-1.5 space-y-1">
                                    @foreach ($kelas as $k => [$nama, $rentang])
                                        <li class="flex items-center gap-2">
                                            <img src="{{ $ikon("$berkas-$k") }}" alt="" width="18" height="21" class="h-[21px] w-[18px] shrink-0">
                                            <span class="flex-1 text-gray-800">{{ $nama }}</span>
                                            @if ($rentang) <span class="text-xs text-gray-600">{{ $rentang }}</span> @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                    <ul class="mt-3 flex flex-wrap justify-center gap-x-6 gap-y-1.5 border-t border-gray-100 pt-3 text-xs text-gray-700">
                        <li class="flex items-center gap-2"><span class="h-3.5 w-[18px] shrink-0 rounded-sm border-2 border-[#1d4ed8] bg-[#3b82f6]/10" aria-hidden="true"></span> Batas daerah aliran sungai (DAS)</li>
                    </ul>
                </div>
            </details>
        </div>
        <script type="application/json" id="data-peta">@json($titik)</script>

        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            @if ($tanpaKoordinat)
                <p class="mt-3 text-sm text-gray-600">{{ $angka($tanpaKoordinat) }} pos aktif belum memiliki koordinat sehingga tidak tampil di peta.</p>
            @endif
        </div>
    </section>

    {{-- 3. TIGA BIDANG: tab horizontal bergambar seperti aplikasi lama. Tanpa skrip semua panel tampil. --}}
    <section id="bidang" aria-labelledby="judul-bidang" class="scroll-mt-16 border-t border-gray-100 bg-langit py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <span class="{{ $label }} bg-violet-50 text-violet-700"><i class="bi bi-info-circle-fill" aria-hidden="true"></i>Informasi SIH3</span>
            <h2 id="judul-bidang" class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950">Tiga bidang pemantauan</h2>
            <p class="mt-2 text-gray-600">Jumlah pos dan instansi walidata pada setiap bidang.</p>

            <div class="mt-8 overflow-hidden rounded-2xl border border-gray-200 bg-white">
                <div role="tablist" aria-label="Bidang pemantauan" class="hidden overflow-x-auto border-b border-gray-200 [.js_&]:flex">
                    @foreach ($bidang as $kunci => $b)
                        <button type="button" role="tab" id="tab-{{ $kunci }}" aria-controls="bidang-{{ $kunci }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}"
                            class="flex min-w-60 flex-1 items-center gap-3 border-b-2 border-transparent px-5 py-4 text-left hover:bg-gray-50 aria-selected:border-primary-500">
                            <img src="{{ asset("images/portal/bidang/$kunci.png") }}" alt="" width="40" height="40" class="size-10">
                            <span>
                                <span class="block font-bold text-gray-950">{{ $b['label'] }}</span>
                                <span class="block text-sm text-gray-600"><span class="tabular-nums">{{ $angka($b['pos']) }}</span> pos · {{ $b['instansi'] }} instansi</span>
                            </span>
                        </button>
                    @endforeach
                </div>

                @foreach ($bidang as $kunci => $b)
                    @php
                        $jenisBidang = collect($kelompok)->filter(fn ($k) => $k[1] === $kunci)->map(fn ($k) => $k[0])->values();
                    @endphp
                    <article id="bidang-{{ $kunci }}" role="tabpanel" aria-labelledby="tab-{{ $kunci }}" tabindex="0"
                        class="grid scroll-mt-24 gap-8 border-b border-gray-200 p-5 last:border-0 sm:p-8 lg:grid-cols-[18rem_minmax(0,1fr)] [.js_&]:border-0">
                        <div class="rounded-2xl bg-langit p-6">
                            <img src="{{ asset("images/portal/bidang/$kunci.png") }}" alt="" width="80" height="80" class="size-20">
                            <h3 class="mt-4 font-bold text-gray-950">{{ $b['label'] }}</h3>
                            <p class="mt-1"><span class="text-5xl font-extrabold tracking-tight tabular-nums text-primary-600">{{ $angka($b['pos']) }}</span> <span class="font-semibold text-gray-700">pos dari {{ $b['instansi'] }} instansi</span></p>
                            <p class="mt-4 text-sm leading-relaxed text-gray-700">{{ $b['definisi'] }}</p>
                        </div>
                        <ul class="grid content-start gap-x-10 sm:grid-cols-2">
                            @foreach ($b['rincian'] as $r)
                                <li class="border-b border-gray-100 py-4">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="min-w-0 text-sm font-semibold text-gray-900">{{ $r['nama'] }}</span>
                                        <span class="shrink-0 text-lg font-bold tabular-nums text-gray-950">{{ $angka($r['jumlah']) }}</span>
                                    </div>
                                    <p class="mt-1 flex flex-wrap gap-x-4 text-xs text-gray-600">
                                        @foreach ($r['jenis'] as $j => $n)
                                            <span class="flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-primary-500" aria-hidden="true"></span>{{ $j }}: {{ $n }}</span>
                                        @endforeach
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 4. WALIDATA: deretan logo bernama dalam satu kartu, seperti aplikasi lama; logo menuju website instansi bila ada. --}}
    <section aria-labelledby="judul-walidata" class="border-t border-gray-100 bg-white py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <span class="{{ $label }} bg-amber-50 text-amber-800"><i class="bi bi-people-fill" aria-hidden="true"></i>Kolaborasi</span>
            <h2 id="judul-walidata" class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950">Walidata SIH3</h2>
            <p class="mt-2 text-gray-600">Instansi yang berkolaborasi dalam SIH3 Wilayah Sungai Serayu Opak.</p>
            <ul class="mt-8 flex flex-wrap justify-center gap-x-4 gap-y-6 rounded-2xl border border-gray-200 p-6 sm:gap-x-8">
                @foreach ($walidata as $w)
                    @php
                        $logo = file_exists(public_path("images/walidata/{$w->id}.png")) ? asset("images/walidata/{$w->id}.png") : null;
                    @endphp
                    <li class="w-24 text-center sm:w-28">
                        <a @if ($w->website) href="{{ $w->website }}" target="_blank" rel="noopener" @endif class="group flex flex-col items-center gap-2 rounded-xl p-1">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="" width="64" height="64" loading="lazy" class="size-16 object-contain transition group-hover:scale-105 motion-reduce:transition-none">
                            @else
                                <span class="flex size-16 items-center justify-center rounded-full bg-gray-100 text-2xl font-bold text-gray-700" aria-hidden="true">{{ mb_substr($w->nama, 0, 1) }}</span>
                            @endif
                            <span class="text-[11px] font-bold uppercase leading-tight text-gray-900 group-hover:text-primary-700">{{ $w->nama }}</span>
                            @if ($w->website) <span class="sr-only">(website, tab baru)</span> @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    @push('skrip')
        @vite('resources/js/peta.js')
        <script>
            // Legenda peta tertutup di ponsel agar peta yang sempit tidak tertutup; terbuka di layar lebar.
            if (matchMedia('(max-width: 639px)').matches) document.getElementById('legenda').open = false;

            // Tab tiga bidang: klik dan panah kiri/kanan memilih tab; alamat #bidang-* membuka tab itu.
            const tab = [...document.querySelectorAll('[role=tab]')];
            const pilih = (t, fokus = false) => {
                tab.forEach(x => {
                    const aktif = x === t;
                    x.setAttribute('aria-selected', aktif);
                    x.tabIndex = aktif ? 0 : -1;
                    document.getElementById(x.getAttribute('aria-controls')).hidden = !aktif;
                });
                if (fokus) t.focus();
            };
            tab.forEach((t, i) => {
                t.addEventListener('click', () => pilih(t));
                t.addEventListener('keydown', e => {
                    const arah = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
                    if (arah) { e.preventDefault(); pilih(tab[(i + arah + tab.length) % tab.length], true); }
                });
            });
            const dariAlamat = () => tab.find(t => location.hash === '#' + t.getAttribute('aria-controls'));
            pilih(dariAlamat() ?? tab[0]);
            addEventListener('hashchange', () => dariAlamat() && pilih(dariAlamat()));
        </script>
    @endpush
</x-layouts.portal>

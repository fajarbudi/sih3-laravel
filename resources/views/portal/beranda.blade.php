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
    // Gaya "fresh" (permintaan user 2026-11-22), sebahasa dengan jendela detail pos: pil, cincin tipis, gradasi biru muda.
    $label = 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider ring-1 ring-inset';
    $gayaJudul = 'mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl';
    $tombol = 'inline-flex min-h-10 items-center gap-1.5 rounded-full bg-gray-100 px-4 text-sm font-semibold text-gray-800 ring-1 ring-inset ring-transparent hover:bg-gray-200/70';
    $tombolMode = "$tombol justify-center aria-pressed:bg-primary-600 aria-pressed:text-white aria-pressed:shadow-md aria-pressed:shadow-primary-600/25";
@endphp

<x-layouts.portal :hero-gelap="true">
    {{-- 1. HERO: foto bendung selebar layar di bawah header transparan, judul di tengah. --}}
    <section class="relative isolate -mt-16 overflow-hidden bg-primary-950 pt-16 text-center text-white lg:-mt-[4.5rem] lg:pt-[4.5rem]">
        <img src="{{ asset('images/portal/hero-1920.jpg') }}" srcset="{{ asset('images/portal/hero-960.jpg') }} 960w, {{ asset('images/portal/hero-1920.jpg') }} 1920w"
            sizes="100vw" alt="" width="1920" height="1103" fetchpriority="high" class="absolute inset-0 -z-20 size-full object-cover">
        {{-- Foto dibiarkan jernih (permintaan user): hanya gradasi tipis agar header & judul terbaca, ditambah bayangan teks. --}}
        <div class="absolute inset-0 -z-10 bg-linear-to-b from-primary-950/60 via-primary-950/30 to-primary-950/45" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_55%_45%_at_50%_48%,rgb(6_26_53/0.45),transparent)]" aria-hidden="true"></div>
        <div class="mx-auto max-w-4xl px-4 pb-28 pt-14 [text-shadow:0_2px_18px_rgb(6_26_53/0.55)] sm:pb-36 sm:pt-20">
            <h1>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-amber-300 ring-1 ring-inset ring-white/20 backdrop-blur-sm sm:text-sm">
                    <span class="size-1.5 rounded-full bg-amber-300" aria-hidden="true"></span>Portal SIH3
                </span>
                <span class="mt-4 block text-[1.75rem] font-extrabold leading-tight tracking-tight text-balance sm:text-5xl">Wilayah Sungai <span class="whitespace-nowrap">Serayu-Bogowonto</span> &amp; <span class="whitespace-nowrap">Progo-Opak-Serang</span></span>
            </h1>
            <span class="mx-auto mt-6 block h-1 w-14 rounded-full bg-amber-300" aria-hidden="true"></span>
            <p class="mx-auto mt-6 max-w-xl text-lg text-white/90">Portal publikasi informasi pengelolaan hidrologi, hidrometeorologi, dan hidrogeologi.</p>
        </div>
    </section>

    {{-- 2. PETA: judul + jumlah titik, baris filter, peta selebar layar, legenda ikon. --}}
    {{-- Naik menutupi bawah hero dengan sudut membulat (kesan lembar yang ditarik ke atas). --}}
    <section id="peta" aria-labelledby="judul-peta" class="relative -mt-10 scroll-mt-16 rounded-t-[2rem] bg-white pt-12 sm:-mt-14 sm:rounded-t-[2.5rem] lg:pt-16">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <span class="{{ $label }} bg-primary-50 text-primary-700 ring-primary-100"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>Peta sebaran</span>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="judul-peta" class="{{ $gayaJudul }}">Peta pos pemantauan</h2>
                    <p class="mt-2 max-w-3xl text-gray-600">Sebaran pos hidrologi, hidrometeorologi, dan hidrogeologi. Klik pos untuk melihat detail data.</p>
                </div>
                <p aria-live="polite" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200">
                    <span class="relative flex size-2" aria-hidden="true">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-60 motion-reduce:hidden"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-green-500"></span>
                    </span>Menampilkan <strong id="jumlah-titik" class="font-bold tabular-nums text-gray-950">{{ $angka(count($titik)) }}</strong> titik pos
                </p>
            </div>

            {{-- Baris filter seperti aplikasi lama. Dropdown jenis per bidang memakai <details> (data-dropdown: tertutup saat klik di luar/Escape). --}}
            {{-- Ponsel: kisi 2 kolom yang rapi (mode | bidang | Batas DAS | walidata selebar baris); layar lebar: satu baris. --}}
            <div class="mt-6 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                <div class="col-span-2 grid grid-cols-2 gap-2 sm:inline-flex" role="group" aria-label="Mode tampilan peta">
                    <button type="button" data-mode="semua" aria-pressed="true" class="{{ $tombolMode }}">Tampilkan semua</button>
                    <button type="button" data-mode="peringatan" aria-pressed="false" class="{{ $tombolMode }}">Pos peringatan <span class="tabular-nums">({{ $angka($jumlahPeringatan) }})</span></button>
                </div>
                <span class="mx-1 hidden h-6 w-px bg-gray-200 sm:block" aria-hidden="true"></span>
                @foreach ($bidang as $kunciBidang => $b)
                    <details class="relative" data-dropdown>
                        <summary class="{{ $tombol }} w-full list-none justify-between sm:w-auto [&::-webkit-details-marker]:hidden">{{ $b['label'] }} <i class="bi bi-chevron-down text-xs" aria-hidden="true"></i></summary>
                        {{-- Dropdown di kolom kanan kisi ponsel dirapatkan ke kanan agar tidak keluar layar. --}}
                        <fieldset class="absolute {{ $loop->even ? 'right-0 sm:left-0 sm:right-auto' : 'left-0' }} top-full z-[1100] mt-2 w-64 rounded-2xl bg-white p-2 shadow-xl shadow-gray-950/10 ring-1 ring-gray-950/5">
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
                <select id="filter-walidata" class="{{ $tombol }} col-span-2 order-last pr-8 sm:order-none sm:max-w-56">
                    <option value="">Semua walidata</option>
                    @foreach ($walidata as $w)
                        <option value="{{ $w->id }}">{{ $w->nama }}</option>
                    @endforeach
                </select>
                <span class="mx-1 hidden h-6 w-px bg-gray-200 sm:block" aria-hidden="true"></span>
                <button type="button" data-lapisan="das" aria-pressed="true" class="{{ $tombol }} justify-center aria-pressed:bg-primary-50 aria-pressed:text-primary-700 aria-pressed:ring-primary-200">
                    <i class="bi bi-bounding-box" aria-hidden="true"></i> Batas DAS
                </button>
            </div>
        </div>

        {{-- Peta selebar layar (permintaan user). Tinggi tetap agar tata letak tidak bergeser; isolate menahan z-index Leaflet. --}}
        <div class="relative isolate mt-6 h-[480px] border-y border-gray-200 bg-gray-100 sm:h-[600px] lg:h-[680px]">
            @include('portal.peta.isi', ['layarPenuh' => false])
        </div>
        <script type="application/json" id="data-peta">@json($titik)</script>

        @include('portal.peta.detail-pos')

        {{-- <div class="mx-auto max-w-7xl px-4 lg:px-8">
            @if ($tanpaKoordinat)
                <p class="mt-3 text-sm text-gray-600">{{ $angka($tanpaKoordinat) }} pos aktif belum memiliki koordinat sehingga tidak tampil di peta.</p>
            @endif
        </div> --}}
    </section>

    {{-- 3. TIGA BIDANG: tab bergambar seperti aplikasi lama, tampil sebagai tiga kartu; panel dalam satu kartu besar.
         Tanpa skrip semua panel tampil. --}}
    <section id="bidang" aria-labelledby="judul-bidang" class="scroll-mt-16 bg-linear-to-b from-white to-langit py-16 lg:py-20">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <span class="{{ $label }} bg-violet-50 text-violet-700 ring-violet-100"><i class="bi bi-info-circle-fill" aria-hidden="true"></i>Informasi SIH3</span>
            <h2 id="judul-bidang" class="{{ $gayaJudul }}">Tiga bidang pemantauan</h2>
            <p class="mt-2 text-gray-600">Jumlah pos dan instansi walidata pada setiap bidang.</p>

            <div role="tablist" aria-label="Bidang pemantauan" class="mt-8 hidden grid-cols-3 gap-2 sm:gap-4 [.js_&]:grid">
                @foreach ($bidang as $kunci => $b)
                    <button type="button" role="tab" id="tab-{{ $kunci }}" aria-controls="bidang-{{ $kunci }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}"
                        class="flex min-w-0 flex-col items-center gap-2 rounded-2xl bg-white/60 p-3 text-center ring-1 ring-gray-200 transition hover:bg-white aria-selected:bg-white aria-selected:shadow-lg aria-selected:shadow-primary-900/5 aria-selected:ring-2 aria-selected:ring-primary-500 motion-reduce:transition-none sm:flex-row sm:gap-4 sm:p-4 sm:text-left">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-langit sm:size-14">
                            <img src="{{ asset("images/portal/bidang/$kunci.png") }}" alt="" width="40" height="40" class="size-8 sm:size-10">
                        </span>
                        <span class="min-w-0">
                            {{-- Ponsel: tiga tab muat selebar layar; "Hidrometeorologi" boleh patah setelah "Hidro", jumlah tampil di panel. --}}
                            <span class="block text-sm font-bold text-gray-950 sm:text-base">{!! str_replace('Hidro', 'Hidro<wbr>', e($b['label'])) !!}</span>
                            <span class="hidden text-sm text-gray-600 sm:block"><span class="tabular-nums">{{ $angka($b['pos']) }}</span> pos · {{ $b['instansi'] }} instansi</span>
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="mt-4 rounded-3xl bg-white shadow-sm shadow-gray-950/5 ring-1 ring-gray-200 sm:mt-5">
                @foreach ($bidang as $kunci => $b)
                    <article id="bidang-{{ $kunci }}" role="tabpanel" aria-labelledby="tab-{{ $kunci }}" tabindex="0"
                        class="grid scroll-mt-24 gap-6 border-b border-gray-100 p-4 last:border-0 sm:gap-8 sm:p-6 lg:grid-cols-[19rem_minmax(0,1fr)] lg:p-8 [.js_&]:border-0">
                        <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-primary-50 via-langit to-white p-6 ring-1 ring-primary-100">
                            <img src="{{ asset("images/portal/bidang/$kunci.png") }}" alt="" width="80" height="80" class="size-16 sm:size-20">
                            <h3 class="mt-4 font-bold text-gray-950">{{ $b['label'] }}</h3>
                            <p class="mt-1"><span class="text-5xl font-extrabold tracking-tight tabular-nums text-primary-600">{{ $angka($b['pos']) }}</span> <span class="font-semibold text-gray-700">pos dari {{ $b['instansi'] }} instansi</span></p>
                            <p class="mt-4 text-sm leading-relaxed text-gray-700">{{ $b['definisi'] }}</p>
                        </div>
                        <ul class="grid content-start gap-3 sm:grid-cols-2">
                            @foreach ($b['rincian'] as $r)
                                <li class="rounded-2xl border border-gray-100 bg-gray-50/70 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="min-w-0 text-sm font-bold text-gray-900">{{ $r['nama'] }}</span>
                                        <span class="shrink-0 text-2xl font-extrabold leading-none tracking-tight tabular-nums text-gray-950">{{ $angka($r['jumlah']) }}</span>
                                    </div>
                                    <p class="mt-3 flex flex-wrap gap-1.5 text-xs text-gray-700">
                                        @foreach ($r['jenis'] as $j => $n)
                                            <span class="rounded-full bg-white px-2.5 py-0.5 ring-1 ring-gray-200">{{ $j }} <strong class="font-bold tabular-nums text-gray-950">{{ $n }}</strong></span>
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

    {{-- 4. WALIDATA: logo bernama seperti aplikasi lama, tiap instansi dalam kartu; logo menuju website instansi bila ada. --}}
    <section aria-labelledby="judul-walidata" class="bg-langit pb-20 pt-4 lg:pb-24">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <span class="{{ $label }} bg-amber-50 text-amber-800 ring-amber-200"><i class="bi bi-people-fill" aria-hidden="true"></i>Kolaborasi</span>
            <h2 id="judul-walidata" class="{{ $gayaJudul }}">Walidata SIH3</h2>
            <p class="mt-2 text-gray-600">Instansi yang berkolaborasi dalam SIH3 Wilayah Sungai Serayu Opak.</p>
            <ul class="mt-8 flex flex-wrap justify-center gap-2 sm:gap-4">
                @foreach ($walidata as $w)
                    @php
                        $berkasLogo = public_path("images/walidata/{$w->id}.png");
                        $logo = file_exists($berkasLogo) ? asset("images/walidata/{$w->id}.png") : null;
                        [$lebarLogo, $tinggiLogo] = $logo ? getimagesize($berkasLogo) : [0, 0];
                    @endphp
                    <li class="w-[calc((100%-1rem)/3)] sm:w-44">
                        <a @if ($w->website) href="{{ $w->website }}" target="_blank" rel="noopener" @endif
                            class="group flex h-full flex-col items-center gap-2 rounded-2xl bg-white px-1.5 py-4 text-center sm:gap-3 sm:px-3 sm:py-5 ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary-900/5 hover:ring-primary-200 motion-reduce:transition-none">
                            {{-- Logo disamakan TINGGINYA (lebar mengikuti bentuk logo), seperti portal live; sumber 128 px tinggi. --}}
                            @if ($logo)
                                <img src="{{ $logo }}" alt="" width="{{ $lebarLogo }}" height="{{ $tinggiLogo }}" loading="lazy" class="h-12 w-auto max-w-full object-contain sm:h-20">
                            @else
                                <span class="flex size-16 items-center justify-center rounded-full bg-gray-100 text-2xl font-bold text-gray-700 sm:size-20" aria-hidden="true">{{ mb_substr($w->nama, 0, 1) }}</span>
                            @endif
                            <span class="text-[10px] font-bold uppercase leading-tight text-gray-900 group-hover:text-primary-700 sm:text-xs">{{ $w->nama }}</span>
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

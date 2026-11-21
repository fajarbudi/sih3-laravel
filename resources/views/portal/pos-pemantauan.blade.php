{{--
    Peta Pos Pemantauan layar penuh (pengganti info/pos_pemantauan portal live). Susunan mengikuti live: peta memenuhi layar
    di bawah header, panel filter mengambang di kiri (ponsel: lembar di bawah peta, tertutup saat awal), kotak cari desa di
    kanan atas, legenda di bawah, zoom dan Peta/Satelit di kanan bawah. Gaya mengikuti tampilan "fresh" portal.
    Lapisan Wilayah kekeringan dan EWS longsor BPBD di live belum dibuat, jadi belum ada sakelarnya.
    Logika peta (filter, mode, cari desa, jendela detail) sama dengan beranda: resources/js/peta.js.
--}}
@php
    $kelompok = App\Http\Controllers\PortalController::KELOMPOK;
    $angka = fn ($n) => number_format($n, 0, ',', '.');
    $berkasIkon = ['awlr' => 'dugaair', 'kualitas_air' => 'kualitasair', 'arr' => 'curahhujan', 'klimatologi' => 'klimatologi', 'mat' => 'mat'];
    $judulBlok = 'text-xs font-bold uppercase tracking-wider text-gray-500';
    $tombolMode = 'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-full text-sm font-semibold text-gray-700 hover:text-gray-950 aria-pressed:bg-white aria-pressed:text-gray-950 aria-pressed:shadow-sm';
@endphp

<x-layouts.portal judul="Peta Pos Pemantauan" :tanpa-kaki="true"
    deskripsi="Peta sebaran pos hidrologi, hidrometeorologi, dan hidrogeologi Wilayah Sungai Serayu Opak beserta status terkininya.">
    {{-- Tinggi = layar dikurangi header (termasuk garis bawah 1px), agar halaman tidak tergulir. --}}
    <div class="relative flex h-[calc(100dvh-4rem-1px)] flex-col lg:h-[calc(100dvh-4.5rem-1px)]">
        <div class="relative isolate min-h-0 flex-1 bg-gray-100">
            @include('portal.peta.isi', ['layarPenuh' => true])
        </div>

        {{-- Panel filter (gaya "fresh"). data-tertutup = hanya kepala yang tampil (ponsel: lembar bawah; layar lebar: kartu di kiri atas). --}}
        <aside id="panel-peta" aria-label="Filter peta pos pemantauan"
            class="group/panel relative z-[600] flex max-h-[64dvh] shrink-0 flex-col overflow-hidden rounded-t-[1.75rem] bg-white shadow-[0_-12px_32px_-12px_rgb(16_24_40/0.35)] ring-1 ring-gray-950/5 md:absolute md:bottom-4 md:left-4 md:top-4 md:max-h-none md:w-[23rem] md:rounded-[1.75rem] md:shadow-2xl md:shadow-gray-950/20 md:data-tertutup:bottom-auto">
            {{-- Kepala: gradasi biru muda, jumlah titik besar, chip waktu status. Garis pegangan hanya di ponsel (lembar bawah). --}}
            <div class="relative bg-linear-to-br from-primary-50 via-langit to-white px-5 pb-5 pt-3 md:pt-5">
                <span class="mx-auto mb-3 block h-1.5 w-10 rounded-full bg-gray-300 md:hidden" aria-hidden="true"></span>
                <div class="flex items-start gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white text-lg text-primary-600 shadow-sm ring-1 ring-primary-100" aria-hidden="true">
                        <i class="bi bi-geo-alt-fill"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-primary-600">Geospasial</p>
                        <h1 class="text-lg font-extrabold leading-snug tracking-tight text-gray-950">Peta Pos Pemantauan</h1>
                    </div>
                    <button type="button" id="panel-tombol" aria-expanded="true" aria-controls="panel-isi" aria-label="Sembunyikan filter"
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-gray-700 shadow-sm ring-1 ring-gray-200 hover:text-gray-950 hover:ring-gray-300">
                        <i class="bi bi-chevron-down transition group-data-tertutup/panel:rotate-180 motion-reduce:transition-none md:rotate-180 md:group-data-tertutup/panel:rotate-0" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="mt-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                    <p aria-live="polite" class="text-sm text-gray-700">
                        <strong id="jumlah-titik" class="block text-3xl font-extrabold leading-none tracking-tight tabular-nums text-gray-950">{{ $angka(count($titik)) }}</strong>
                        titik pos ditampilkan
                    </p>
                    <p class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-medium text-gray-700 shadow-sm ring-1 ring-gray-200">
                        <span class="relative flex size-2" aria-hidden="true">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-green-400 opacity-60 motion-reduce:hidden"></span>
                            <span class="relative inline-flex size-2 rounded-full bg-green-500"></span>
                        </span>
                        Diperbarui {{ now()->format('H.i') }}
                    </p>
                </div>
            </div>

            <div id="panel-isi" class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 pb-6 pt-5 group-data-tertutup/panel:hidden">
                <div class="grid grid-cols-2 gap-1 rounded-full bg-gray-100 p-1" role="group" aria-label="Mode tampilan peta">
                    <button type="button" data-mode="semua" aria-pressed="true" class="{{ $tombolMode }}">Semua pos</button>
                    <button type="button" data-mode="peringatan" aria-pressed="false" class="{{ $tombolMode }}">
                        Status terkini <span class="rounded-full bg-primary-50 px-1.5 text-xs font-bold tabular-nums text-primary-700">{{ $angka($jumlahPeringatan) }}</span>
                    </button>
                </div>

                <fieldset>
                    <legend class="{{ $judulBlok }}">Jenis pos</legend>
                    @foreach (App\Enums\TipePos::cases() as $tipe)
                        <p class="mb-2 mt-4 text-xs font-semibold text-gray-600">{{ $tipe->label() }}</p>
                        {{-- Ubin yang bisa diketuk (checkbox tersembunyi): aktif = biru muda bercincin, mati = abu-abu redup. --}}
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($kelompok as $kel => [$labelJenis, $tipeKel])
                                @continue($tipeKel !== $tipe->value)
                                <label class="group/jenis relative flex cursor-pointer flex-col gap-2 rounded-2xl bg-gray-50 p-3 opacity-60 ring-1 ring-gray-200 transition hover:opacity-90 has-checked:bg-primary-50/70 has-checked:opacity-100 has-checked:ring-primary-200 has-focus-visible:ring-2 has-focus-visible:ring-primary-500 motion-reduce:transition-none">
                                    <input type="checkbox" checked data-filter-kel="{{ $kel }}" class="sr-only">
                                    <span class="flex items-start justify-between gap-2">
                                        <img src="{{ asset("images/portal/penanda/{$berkasIkon[$kel]}.png") }}" alt="" width="24" height="27" class="h-[27px] w-6 shrink-0">
                                        <span class="flex size-5 items-center justify-center rounded-full bg-white text-[11px] text-transparent ring-1 ring-gray-300 group-has-checked/jenis:bg-primary-600 group-has-checked/jenis:text-white group-has-checked/jenis:ring-primary-600" aria-hidden="true">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                    </span>
                                    <span>
                                        <span class="block text-xl font-extrabold leading-none tracking-tight tabular-nums text-gray-950">{{ $angka($jumlahPerKelompok[$kel] ?? 0) }}</span>
                                        <span class="mt-1 block text-xs font-semibold text-gray-700">{{ $labelJenis }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </fieldset>

                <div>
                    <label for="filter-walidata" class="{{ $judulBlok }}">Walidata</label>
                    <select id="filter-walidata" class="mt-2 block min-h-11 w-full rounded-full border-0 bg-gray-100 px-4 text-sm font-medium text-gray-900 focus:ring-2 focus:ring-primary-500">
                        <option value="">Semua walidata</option>
                        @foreach ($walidata as $w)
                            <option value="{{ $w->id }}">{{ $w->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <p class="{{ $judulBlok }}">Lapisan peta</p>
                    <button type="button" data-lapisan="das" aria-pressed="true" class="group/das mt-2 flex min-h-14 w-full items-center gap-3 rounded-2xl bg-gray-50 px-3 text-left ring-1 ring-gray-200 hover:bg-gray-100 aria-pressed:bg-primary-50/70 aria-pressed:ring-primary-200">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-gray-200" aria-hidden="true">
                            <span class="h-3.5 w-4 rounded-sm border-2 border-[#1d4ed8] bg-[#3b82f6]/10"></span>
                        </span>
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-gray-900">Batas DAS</span>
                            <span class="block text-xs text-gray-500">Daerah aliran sungai 2025</span>
                        </span>
                        {{-- Sakelar: aria-pressed = menyala. --}}
                        <span class="relative h-6 w-10 shrink-0 rounded-full bg-gray-300 transition group-aria-pressed/das:bg-primary-600 motion-reduce:transition-none" aria-hidden="true">
                            <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition group-aria-pressed/das:translate-x-4 motion-reduce:transition-none"></span>
                        </span>
                    </button>
                </div>

                @if ($tanpaKoordinat)
                    <p class="flex gap-2 rounded-2xl bg-amber-50 px-3.5 py-3 text-xs text-amber-900 ring-1 ring-amber-200">
                        <i class="bi bi-info-circle mt-px" aria-hidden="true"></i>{{ $angka($tanpaKoordinat) }} pos aktif belum memiliki koordinat sehingga tidak tampil di peta.
                    </p>
                @endif
            </div>
        </aside>
    </div>

    <script type="application/json" id="data-peta">@json($titik)</script>
    @include('portal.peta.detail-pos')

    @push('skrip')
        @vite('resources/js/peta.js')
        <script>
            // Panel filter: terbuka di layar lebar, tertutup (hanya kepala) di ponsel agar peta terlihat luas.
            const panel = document.getElementById('panel-peta');
            const tombolPanel = document.getElementById('panel-tombol');
            const aturPanel = buka => {
                panel.toggleAttribute('data-tertutup', !buka);
                tombolPanel.setAttribute('aria-expanded', String(buka));
                tombolPanel.setAttribute('aria-label', buka ? 'Sembunyikan filter' : 'Tampilkan filter');
            };
            aturPanel(!matchMedia('(max-width: 767px)').matches);
            tombolPanel.addEventListener('click', () => aturPanel(panel.hasAttribute('data-tertutup')));
        </script>
    @endpush
</x-layouts.portal>

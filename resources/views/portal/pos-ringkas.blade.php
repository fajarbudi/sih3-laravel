{{--
    Isi jendela detail pos di peta beranda (PortalController@ringkasPos, dimuat resources/js/peta.js).
    Isi mengikuti portal/detilpos lama: nilai terkini + kelas, data pos, lokasi, walidata + kontak, foto pos.
    Tampilan "lebih fresh" (permintaan user): lokasi di atas, ubin nilai bergradasi, data pos sebagai kartu kecil berikon,
    kontak walidata sebagai tombol pil.
    Tautan "Lihat peta lokasi" dan arsip data lama belum ada karena halaman datasheet/arsip belum dibuat.
--}}
@php
    $berkas = ['awlr' => 'dugaair', 'kualitas_air' => 'kualitasair', 'arr' => 'curahhujan', 'klimatologi' => 'klimatologi', 'mat' => 'mat'][$t['kel']];
    $w = $pos->walidata;
    $logo = $w && file_exists(public_path("images/walidata/{$w->id}.png")) ? asset("images/walidata/{$w->id}.png") : null;
    $tersedia = collect([$pos->data_tersedia_dari, $pos->data_tersedia_sampai])->filter()->unique()->join('–');
    $lokasi = collect([
        $pos->desa ? "Desa {$pos->desa}" : null,
        $pos->kecamatan ? "Kec. {$pos->kecamatan}" : null,
        $pos->kabupaten, $pos->provinsi,
    ])->filter()->join(', ');
    // [label, ikon Bootstrap Icons, nilai]
    $dataPos = array_filter([
        ['Tipe', 'bi-diagram-3', $pos->tipe->label()],
        ['Wilayah sungai', 'bi-water', $pos->wilayah_sungai],
        ['Daerah aliran sungai', 'bi-bounding-box', $pos->das],
        ['Sungai', 'bi-droplet', $pos->sungai],
        ['Elevasi', 'bi-triangle', $pos->elevasi !== null ? number_format($pos->elevasi, 0, ',', '.').' mdpl' : null],
        ['Ketersediaan data', 'bi-calendar3', $tersedia ?: null],
    ], fn ($d) => $d[2]);
    $kontak = $w ? array_filter([
        ['Website', 'bi-globe2', $w->website, 'bg-primary-50 text-primary-700 hover:bg-primary-100'],
        ['Permintaan data', 'bi-file-earmark-arrow-down', $w->url_ppid, 'bg-primary-50 text-primary-700 hover:bg-primary-100'],
        ['WhatsApp', 'bi-whatsapp', $w->whatsapp ? 'https://wa.me/'.preg_replace('/\D/', '', $w->whatsapp) : null, 'bg-green-50 text-green-800 hover:bg-green-100'],
    ], fn ($k) => $k[2]) : [];
@endphp

@if ($lokasi)
    <p class="-mt-1 mb-5 flex items-start gap-2 text-sm text-gray-700">
        <i class="bi bi-geo-alt-fill mt-px text-red-500" aria-hidden="true"></i>{{ $lokasi }}
    </p>
@endif

<div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="min-w-0 space-y-5">
        {{-- Ubin nilai terkini: gradasi biru lembut, angka besar, lencana kelas (ikon sama dengan legenda). --}}
        <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-primary-50 via-langit to-white p-5 ring-1 ring-primary-100 sm:p-6">
            <img src="{{ asset("images/portal/penanda/$berkas.png") }}" alt="" width="28" height="32" class="pointer-events-none absolute -right-4 -top-3 h-32 w-auto opacity-[0.07]">
            <p class="text-sm font-semibold text-primary-700">{{ $t['label'] }}</p>
            @if ($t['nilai'])
                <p class="mt-1 text-5xl font-extrabold tracking-tight tabular-nums text-gray-950">{{ $t['nilai'] }}</p>
            @else
                <p class="mt-2 text-xl font-bold text-gray-500">Belum ada data</p>
            @endif
            @if ($t['kelasLabel'] || $t['hujan24'])
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @if ($t['kelasLabel'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white py-1 pl-1.5 pr-3 text-sm font-bold text-gray-900 shadow-sm ring-1 ring-gray-950/5">
                            <img src="{{ asset("images/portal/penanda/$berkas-{$t['kelas']}.png") }}" alt="" width="18" height="21" class="h-[21px] w-[18px]">{{ $t['kelasLabel'] }}
                        </span>
                    @endif
                    @if ($t['hujan24'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-sm text-gray-700 shadow-sm ring-1 ring-gray-950/5">
                            <i class="bi bi-cloud-rain text-primary-600" aria-hidden="true"></i>24 jam <strong class="font-bold tabular-nums text-gray-950">{{ $t['hujan24'] }}</strong>
                        </span>
                    @endif
                </div>
            @endif
            @if ($t['waktu'])
                <p class="mt-4 flex items-center gap-1.5 text-xs text-gray-600"><i class="bi bi-clock" aria-hidden="true"></i>Diperbarui {{ $t['waktu'] }}</p>
            @endif
        </div>

        {{-- Data pos: kartu kecil berikon. --}}
        @if ($dataPos)
            <dl class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                @foreach ($dataPos as [$label, $ikon, $nilai])
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3">
                        <dt class="flex items-center gap-1.5 text-xs text-gray-600">
                            <i class="bi {{ $ikon }} text-primary-500" aria-hidden="true"></i>{{ $label }}
                        </dt>
                        <dd class="mt-1 font-semibold leading-snug text-gray-950 [overflow-wrap:anywhere]">{!! str_replace('Hidro', 'Hidro<wbr>', e($nilai)) !!}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    <div class="space-y-4">
        @if ($foto)
            <img src="{{ $foto }}" alt="Foto pos {{ $pos->nama }}" loading="lazy" class="aspect-[4/3] w-full rounded-2xl object-cover shadow-sm">
        @endif

        {{-- Walidata: logo + nama, kontak sebagai tombol pil. --}}
        @if ($w)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm shadow-gray-950/5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Walidata</p>
                <div class="mt-3 flex items-center gap-3">
                    @if ($logo)
                        <span class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-gray-50 p-1.5">
                            <img src="{{ $logo }}" alt="" class="max-h-full w-auto object-contain">
                        </span>
                    @endif
                    <p class="font-bold leading-snug text-gray-950">{{ $w->nama_panjang ?: $w->nama }}</p>
                </div>
                @if ($kontak)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($kontak as [$teks, $ikon, $url, $warna])
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3.5 text-sm font-semibold {{ $warna }}">
                                <i class="bi {{ $ikon }}" aria-hidden="true"></i>{{ $teks }}<span class="sr-only"> (tab baru)</span>
                            </a>
                        @endforeach
                    </div>
                @endif
                @if ($w->kontak)
                    <p class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3 text-sm text-gray-700">
                        <i class="bi bi-telephone text-gray-500" aria-hidden="true"></i>{{ $w->kontak }}
                    </p>
                @endif
            </div>
        @endif
    </div>
</div>

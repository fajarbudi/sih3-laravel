{{--
    Tab "Arsip data" di jendela detail datasheet (PortalController@arsipPos), pengganti portal/detilarsip lama:
    daftar tahun arsip pos; data diminta lewat website / layanan permintaan data (PPID) walidata, seperti lama.
--}}
@php $w = $pos->walidata; @endphp

@if ($tahun->isEmpty())
    <div class="rounded-2xl bg-gray-50 px-5 py-10 text-center">
        <i class="bi bi-archive text-3xl text-gray-400" aria-hidden="true"></i>
        <p class="mt-2 font-semibold text-gray-800">Belum ada arsip data untuk pos ini.</p>
    </div>
@else
    <p class="text-sm text-gray-700">Arsip data tersedia untuk <strong class="text-gray-950">{{ $tahun->count() }} tahun</strong>:</p>
    <ul class="mt-3 flex flex-wrap gap-2">
        @foreach ($tahun as $t)
            <li class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-primary-50 px-3.5 text-sm font-bold tabular-nums text-primary-800 ring-1 ring-inset ring-primary-100">
                <i class="bi bi-calendar3 text-primary-500" aria-hidden="true"></i>{{ $t }}
            </li>
        @endforeach
    </ul>
@endif

@if ($w && ($w->website || $w->url_ppid))
    <div class="mt-6 rounded-2xl border border-gray-200 p-4 sm:p-5">
        <p class="text-sm text-gray-700">Data pos ini dikelola <strong class="text-gray-950">{{ $w->nama_panjang ?: $w->nama }}</strong>. Ajukan permintaan data melalui walidata:</p>
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($w->url_ppid)
                <a href="{{ $w->url_ppid }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700">
                    <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i>Permintaan data<span class="sr-only"> (tab baru)</span>
                </a>
            @endif
            @if ($w->website)
                <a href="{{ $w->website }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-primary-50 px-4 text-sm font-semibold text-primary-700 hover:bg-primary-100">
                    <i class="bi bi-globe2" aria-hidden="true"></i>Website {{ $w->nama }}<span class="sr-only"> (tab baru)</span>
                </a>
            @endif
        </div>
    </div>
@endif

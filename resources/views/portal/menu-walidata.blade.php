{{--
    Isi menu Walidata (portal lama: Website, Permintaan Data = URL PPID, Kontak WA per walidata). Hanya tautan yang datanya ada.
    Walidata tanpa tautan tetap tampil sebagai nama, agar daftar pengelola data lengkap.
    Tiap instansi: logo + nama, tautan sebagai pil kecil berikon (gaya "fresh" portal). Kisi kolomnya diatur pemanggil.
--}}
@foreach ($menuWalidata as $w)
    @php
        $logo = file_exists(public_path("images/walidata/{$w->id}.png")) ? asset("images/walidata/{$w->id}.png") : null;
        $tautan = array_filter([
            ['Website', 'bi-globe2', $w->website, 'bg-primary-50 text-primary-700 hover:bg-primary-100'],
            ['Permintaan data', 'bi-file-earmark-arrow-down', $w->url_ppid, 'bg-primary-50 text-primary-700 hover:bg-primary-100'],
            ['WhatsApp', 'bi-whatsapp', $w->whatsapp ? 'https://wa.me/'.preg_replace('/\D/', '', $w->whatsapp) : null, 'bg-green-50 text-green-800 hover:bg-green-100'],
        ], fn ($t) => $t[2]);
    @endphp
    <div class="flex gap-2.5 rounded-xl p-2 hover:bg-gray-50 lg:gap-3 lg:p-2.5">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white p-1 ring-1 ring-gray-200 lg:size-10">
            @if ($logo)
                <img src="{{ $logo }}" alt="" loading="lazy" class="max-h-full w-auto object-contain">
            @else
                <span class="text-sm font-bold text-gray-600" aria-hidden="true">{{ mb_substr($w->nama, 0, 1) }}</span>
            @endif
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold leading-snug text-gray-900">{{ $w->nama }}</p>
            @if ($tautan)
                <div class="mt-1.5 flex flex-wrap gap-1">
                    @foreach ($tautan as [$teks, $ikon, $url, $warna])
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex min-h-8 items-center gap-1 rounded-full px-2 text-[11px] font-semibold lg:min-h-7 {{ $warna }}">
                            <i class="bi {{ $ikon }}" aria-hidden="true"></i>{{ $teks }}<span class="sr-only"> {{ $w->nama }} (tab baru)</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endforeach

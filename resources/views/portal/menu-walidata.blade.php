{{--
    Isi menu Walidata (portal lama: Website, Permintaan Data = URL PPID, Kontak WA per walidata). Hanya tautan yang datanya ada.
    Walidata tanpa tautan tetap tampil sebagai nama, agar daftar pengelola data lengkap.
--}}
@foreach ($menuWalidata as $w)
    <div class="border-b border-gray-100 py-1 last:border-0">
        <p class="px-3 pt-1.5 text-sm font-semibold text-gray-900">{{ $w->nama }}</p>
        <div class="flex flex-wrap gap-x-1 px-1 pb-1">
            @foreach (array_filter([
                'Website' => $w->website,
                'Permintaan data' => $w->url_ppid,
                'WhatsApp' => $w->whatsapp ? 'https://wa.me/'.preg_replace('/\D/', '', $w->whatsapp) : null,
            ]) as $teks => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-sm text-primary-600 hover:bg-primary-50 lg:min-h-8">
                    {{ $teks }}<span class="sr-only"> {{ $w->nama }} (tab baru)</span>
                </a>
            @endforeach
        </div>
    </div>
@endforeach

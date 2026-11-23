{{--
    Isi jendela satu edisi buletin (pengganti portal/detilbuletin lama): infografis ukuran penuh, tanggal, sumber,
    keterangan, Unduh gambar/PDF, Kunjungi halaman. Dipakai kartu edisi dan sorotan di beranda buletin.
    Variabel: $b (Buletin dengan lampiran & kategori), $label (nama kategori portal).
--}}
@php
    $gambar = $b->lampiran->where('jenis', 'gambar')->filter(fn ($l) => Storage::disk('public')->exists($l->path));
    $pdf = $b->lampiran->firstWhere('jenis', 'pdf');
    $sampul = $gambar->first();
    $ringkasan = trim(html_entity_decode(strip_tags($b->ringkasan ?? ''), ENT_QUOTES));
@endphp
<template id="buletin-{{ $b->id }}">
    <div class="space-y-3">
        @foreach ($gambar as $g)
            <a href="{{ $g->url() }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200">
                <img src="{{ $g->url() }}" alt="{{ $b->judul }}{{ $gambar->count() > 1 ? ' (gambar '.$loop->iteration.')' : '' }}" class="mx-auto w-auto">
            </a>
        @endforeach
    </div>
    <p class="mt-4 text-sm text-gray-600">{{ $b->kategori?->nama ?? $label }} · {{ $b->tanggal?->translatedFormat('l, j F Y') }}</p>
    @if ($b->sumber) <p class="mt-1 font-bold text-gray-950">{{ $b->sumber }}</p> @endif
    @if ($ringkasan) <p class="mt-2 whitespace-pre-line text-gray-800">{{ $ringkasan }}</p> @endif
    <div class="mt-4 flex flex-wrap gap-2">
        @if ($sampul)
            <a href="{{ $sampul->url() }}" download class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700"><i class="bi bi-download" aria-hidden="true"></i>Unduh gambar</a>
        @endif
        @if ($pdf)
            <a href="{{ $pdf->url() }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-red-50 px-4 text-sm font-bold text-red-700 hover:bg-red-100"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>Unduh PDF</a>
        @endif
        @if ($b->url_sumber)
            <a href="{{ $b->url_sumber }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-gray-100 px-4 text-sm font-bold text-gray-800 hover:bg-gray-200">Kunjungi halaman sumber<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
        @endif
    </div>
</template>

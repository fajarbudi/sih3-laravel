{{--
    Pagination portal publik, senada dengan galeri Koleksi peta: tanpa kotak, dipisah garis tipis di atas,
    tombol berbentuk pil seperti pilihan jenis (halaman aktif biru). Ponsel: nomor diganti "Halaman x dari y".

    Pakai: <x-portal.pagination :data="$peta" satuan="peta" />
--}}
@props(['data', 'satuan' => 'data'])

@php
    $angka = fn ($n) => number_format($n, 0, ',', '.');
    $nomor = $data->onEachSide(1)->linkCollection()->slice(1, -1);
    $tombol = 'inline-flex min-h-10 min-w-10 items-center justify-center gap-1.5 rounded-full px-3 text-sm font-semibold tabular-nums';
    $biasa = 'bg-gray-100 text-gray-800 hover:bg-gray-200/70';
    $aktif = 'bg-primary-600 text-white shadow-md shadow-primary-600/25';
    $mati = 'bg-gray-50 text-gray-400';
@endphp

@if ($data->total() > 0)
    <nav aria-label="Halaman {{ $satuan }}" class="mt-12 flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 pt-6">
        <p class="text-sm text-gray-600 tabular-nums">
            Menampilkan <strong class="font-bold text-gray-950">{{ $angka($data->firstItem()) }}–{{ $angka($data->lastItem()) }}</strong>
            dari <strong class="font-bold text-gray-950">{{ $angka($data->total()) }}</strong> {{ $satuan }}
        </p>

        @if ($data->hasPages())
            <div class="flex items-center gap-1.5">
                @if ($data->onFirstPage())
                    <span class="{{ $tombol }} {{ $mati }}" aria-disabled="true"><i class="bi bi-chevron-left text-xs" aria-hidden="true"></i><span class="max-sm:sr-only">Sebelumnya</span></span>
                @else
                    <a href="{{ $data->previousPageUrl() }}" rel="prev" class="{{ $tombol }} {{ $biasa }}"><i class="bi bi-chevron-left text-xs" aria-hidden="true"></i><span class="max-sm:sr-only">Sebelumnya</span></a>
                @endif

                <ul class="flex items-center gap-1.5 max-sm:hidden">
                    @foreach ($nomor as $link)
                        <li>
                            @if ($link['url'] === null)
                                <span class="{{ $tombol }} text-gray-600" aria-hidden="true">…</span>
                            @elseif ($link['active'])
                                <span class="{{ $tombol }} {{ $aktif }}" aria-current="page"><span class="sr-only">Halaman </span>{{ $link['label'] }}</span>
                            @else
                                <a href="{{ $link['url'] }}" class="{{ $tombol }} {{ $biasa }}"><span class="sr-only">Halaman </span>{{ $link['label'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <span class="px-2 text-sm text-gray-600 tabular-nums sm:hidden">Halaman {{ $data->currentPage() }} dari {{ $data->lastPage() }}</span>

                @if ($data->hasMorePages())
                    <a href="{{ $data->nextPageUrl() }}" rel="next" class="{{ $tombol }} {{ $biasa }}"><span class="max-sm:sr-only">Berikutnya</span><i class="bi bi-chevron-right text-xs" aria-hidden="true"></i></a>
                @else
                    <span class="{{ $tombol }} {{ $mati }}" aria-disabled="true"><span class="max-sm:sr-only">Berikutnya</span><i class="bi bi-chevron-right text-xs" aria-hidden="true"></i></span>
                @endif
            </div>
        @endif
    </nav>
@endif

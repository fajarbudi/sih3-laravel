{{--
    Pagination daftar data. Pakai: <x-admin.pagination :data="$data" /> (LengthAwarePaginator).
    - Kiri: rentang data yang tampil.
    - Kanan: Sebelumnya, nomor halaman (dengan … untuk halaman yang dilompati), Berikutnya.
    - Ponsel: nomor halaman diganti "Halaman x dari y" agar tidak meluber.
    Halaman aktif ditandai latar biru muda + teks biru tebal + cincin (sama dengan pilihan jam di form), bukan warna saja.
--}}
@props(['data'])

@php
    $angka = fn ($n) => number_format($n, 0, ',', '.');
    // linkCollection(): [sebelumnya, ...nomor/…, berikutnya]; ambil bagian nomornya saja.
    $nomor = $data->onEachSide(1)->linkCollection()->slice(1, -1);
    $tombol = 'flex min-h-11 min-w-11 items-center justify-center gap-1.5 rounded-md px-2.5 tabular-nums lg:min-h-8 lg:min-w-8';
    $aktif = 'bg-primary-50 font-semibold text-primary-700 ring-1 ring-primary-500';
    $biasa = 'text-gray-700 hover:bg-gray-100 hover:text-gray-900 active:bg-gray-150';
    $mati = 'text-gray-400';
@endphp

@if ($data->total() > 0)
    <nav aria-label="Halaman data" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-4 lg:px-6">
        <p class="text-gray-600 tabular-nums">
            Menampilkan <span class="font-medium text-gray-900">{{ $angka($data->firstItem()) }}–{{ $angka($data->lastItem()) }}</span>
            dari <span class="font-medium text-gray-900">{{ $angka($data->total()) }}</span> data
        </p>

        @if ($data->hasPages())
            <div class="flex items-center gap-1">
                {{-- Sebelumnya --}}
                @if ($data->onFirstPage())
                    <span class="{{ $tombol }} {{ $mati }}" aria-disabled="true">
                        <i class="bi bi-chevron-left text-xs" aria-hidden="true"></i><span class="max-sm:sr-only">Sebelumnya</span>
                    </span>
                @else
                    <a href="{{ $data->previousPageUrl() }}" rel="prev" class="{{ $tombol }} {{ $biasa }}">
                        <i class="bi bi-chevron-left text-xs" aria-hidden="true"></i><span class="max-sm:sr-only">Sebelumnya</span>
                    </a>
                @endif

                {{-- Nomor halaman (layar >= sm) --}}
                <ul class="flex items-center gap-1 max-sm:hidden">
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

                {{-- Posisi halaman (ponsel) --}}
                <span class="px-2 text-gray-600 tabular-nums sm:hidden">Halaman {{ $data->currentPage() }} dari {{ $data->lastPage() }}</span>

                {{-- Berikutnya --}}
                @if ($data->hasMorePages())
                    <a href="{{ $data->nextPageUrl() }}" rel="next" class="{{ $tombol }} {{ $biasa }}">
                        <span class="max-sm:sr-only">Berikutnya</span><i class="bi bi-chevron-right text-xs" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="{{ $tombol }} {{ $mati }}" aria-disabled="true">
                        <span class="max-sm:sr-only">Berikutnya</span><i class="bi bi-chevron-right text-xs" aria-hidden="true"></i>
                    </span>
                @endif
            </div>
        @endif
    </nav>
@endif

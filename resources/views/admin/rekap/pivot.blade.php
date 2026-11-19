@php
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $tombol = 'flex min-h-11 items-center gap-1.5 rounded-md border border-gray-450 bg-white px-2.5 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 lg:min-h-7';
    $kueri = array_filter(['walidata' => $filter['walidata'] ?? null, 'pos' => $filter['pos'] ?? null, 'tahun' => $filter['tahun']]);
    $diubah = ($filter['walidata'] ?? null) || ($filter['pos'] ?? null) || $filter['tahun'] !== now()->year;
@endphp

<x-layouts.admin :judul="$rekap['judul']" :induk="$rekap['induk']" :penuh="true">
    {{-- Cetak dan Excel memakai filter yang sedang aktif, seperti aplikasi lama. --}}
    <x-slot:aksi>
        <a href="{{ route('admin.rekap.'.$jenis, $kueri + ['format' => 'cetak']) }}" target="_blank" class="{{ $tombol }}">
            <i class="bi bi-printer text-xs" aria-hidden="true"></i> Cetak
        </a>
        <a href="{{ route('admin.rekap.'.$jenis, $kueri + ['format' => 'excel']) }}" class="{{ $tombol }}">
            <i class="bi bi-file-earmark-spreadsheet text-xs" aria-hidden="true"></i> Excel
        </a>
    </x-slot:aksi>

    <x-slot:filter>
        <form method="GET" class="flex flex-1 flex-wrap items-center gap-2">
            <i class="bi bi-funnel text-gray-600" aria-hidden="true"></i>
            @if ($daftarWalidata->isNotEmpty())
                <label for="walidata" class="sr-only">Walidata</label>
                <select id="walidata" name="walidata" onchange="this.form.requestSubmit()" class="{{ $pil }} max-w-48">
                    <option value="">Semua walidata</option>
                    @foreach ($daftarWalidata as $id => $nama)
                        <option value="{{ $id }}" @selected(($filter['walidata'] ?? null) == $id)>{{ $nama }}</option>
                    @endforeach
                </select>
            @endif
            @if ($daftarPos->count() > 1)
                <label for="pos" class="sr-only">Pos</label>
                <select id="pos" name="pos" onchange="this.form.requestSubmit()" class="{{ $pil }} max-w-48">
                    <option value="">Semua pos</option>
                    @foreach ($daftarPos as $id => $nama)
                        <option value="{{ $id }}" @selected(($filter['pos'] ?? null) == $id)>{{ $nama }}</option>
                    @endforeach
                </select>
            @endif
            {{-- Tahun selalu terisi; default tahun berjalan seperti aplikasi lama. --}}
            <label for="tahun" class="sr-only">Tahun</label>
            <select id="tahun" name="tahun" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                @foreach (range(now()->year, 2010) as $tahun)
                    <option value="{{ $tahun }}" @selected($filter['tahun'] === $tahun)>Tahun {{ $tahun }}</option>
                @endforeach
            </select>
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($diubah)
                <a href="{{ route('admin.rekap.'.$jenis) }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ number_format($baris->count(), 0, ',', '.') }} baris</p>
        </form>
    </x-slot:filter>

    @if ($baris->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-table text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">Tidak ada data pada tahun {{ $filter['tahun'] }}</p>
            <p class="mt-1 text-gray-600">Pilih tahun lain atau ubah filter pos.</p>
        </div>
    @else
        {{-- Semua baris setahun tanpa paginasi, seperti aplikasi lama. relative: caption sr-only tidak lolos dari wadah gulir. --}}
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            @include('admin.rekap.pivot-tabel', ['sel' => 'border border-gray-200 px-2.5 py-2'])
        </div>
    @endif
</x-layouts.admin>

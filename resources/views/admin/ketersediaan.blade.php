{{--
    Ketersediaan data (aplikasi lama: vsedia.php): No., POS, Jan s.d. Des berisi jumlah data per bulan; sel kosong = tidak ada data.
--}}
@php
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2.5 py-2';
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $diubah = ($filter['walidata'] ?? null) || ($filter['pos'] ?? null) || $tahun !== now()->year;
@endphp

<x-layouts.admin :judul="'Ketersediaan '.$judul" induk="Ketersediaan Data" :penuh="true">
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
            {{-- Default tahun berjalan seperti aplikasi lama. --}}
            <label for="tahun" class="sr-only">Tahun</label>
            <select id="tahun" name="tahun" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                @foreach (range(now()->year, 2010) as $t)
                    <option value="{{ $t }}" @selected($tahun === $t)>Tahun {{ $t }}</option>
                @endforeach
            </select>
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($diubah)
                <a href="{{ route('admin.ketersediaan.'.$jenis) }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ $pos->count() }} pos</p>
        </form>
    </x-slot:filter>

    @if ($pos->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-calendar3 text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">Tidak ada data pada tahun {{ $tahun }}</p>
            <p class="mt-1 text-gray-600">Pilih tahun lain atau ubah filter.</p>
        </div>
    @else
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <p class="mb-3 text-gray-600">Jumlah data per bulan, tahun {{ $tahun }}.</p>
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">Ketersediaan data {{ $judul }} per bulan tahun {{ $tahun }}</caption>
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" class="{{ $sel }} font-medium">POS</th>
                        @foreach ($bulan as $b)
                            <th scope="col" class="{{ $sel }} min-w-12 text-right font-medium">{{ $b }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pos as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $loop->iteration }}.</td>
                            <td class="{{ $sel }} min-w-40">{{ $p->nama }}</td>
                            @foreach ($bulan as $nomor => $b)
                                <td class="{{ $sel }} text-right tabular-nums">{{ $jumlah[$p->id][$nomor] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.admin>

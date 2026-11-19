@php
    // Angka apa adanya (tanpa pembulatan) seperti aplikasi lama, koma sebagai pemisah desimal.
    $angka = fn ($n) => $n === null ? '' : str_replace('.', ',', rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.'));
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2.5 py-2';
@endphp

<x-layouts.admin :judul="$telemetri['judul']" :induk="$telemetri['induk']" :penuh="true">
    <x-slot:filter>
        <form method="GET" class="flex flex-1 flex-wrap items-center gap-2">
            <i class="bi bi-funnel text-gray-600" aria-hidden="true"></i>
            @if ($daftarPos->count() > 1)
                <label for="pos" class="sr-only">Pos</label>
                <select id="pos" name="pos" onchange="this.form.requestSubmit()" class="{{ $pil }} max-w-56">
                    <option value="">Semua pos PJT</option>
                    @foreach ($daftarPos as $id => $nama)
                        <option value="{{ $id }}" @selected(($filter['pos'] ?? null) == $id)>{{ $nama }}</option>
                    @endforeach
                </select>
            @endif
            <label for="bulan" class="sr-only">Bulan</label>
            <input id="bulan" name="bulan" type="month" value="{{ $filter['bulan'] ?? '' }}" onchange="this.form.requestSubmit()" class="{{ $pil }} w-40">
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($filter)
                <a href="{{ route('admin.'.$jenis) }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ number_format($data->total(), 0, ',', '.') }} data</p>
        </form>
    </x-slot:filter>

    @if ($data->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-broadcast text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">{{ $filter ? 'Tidak ada data untuk filter ini' : 'Belum ada data telemetri PJT' }}</p>
            <p class="mt-1 text-gray-600">{{ $filter ? 'Coba ubah atau hapus filter.' : 'Data masuk otomatis dari server Perum Jasa Tirta I.' }}</p>
        </div>
    @else
        {{-- Seperti aplikasi lama (vawlrpjt.php/varrpjt.php): No., POS, Waktu, nilai; baca-saja. --}}
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">{{ $telemetri['judul'] }}, terbaru di atas</caption>
                <thead class="bg-gray-50 align-bottom text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" class="{{ $sel }} font-medium">POS</th>
                        <th scope="col" class="{{ $sel }} font-medium">Waktu</th>
                        <th scope="col" class="{{ $sel }} text-right font-medium">{{ $telemetri['label'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $b)
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $data->firstItem() + $loop->index }}.</td>
                            <td class="{{ $sel }} min-w-40">{{ $b->pos->nama }}</td>
                            <td class="{{ $sel }} whitespace-nowrap tabular-nums">{{ $b->waktu->translatedFormat('j F Y') }} / {{ $b->waktu->format('H:i') }}</td>
                            <td class="{{ $sel }} text-right tabular-nums">@php($mentah = $b->{$telemetri['kolom']})
                                {{ $angka($mentah === null ? null : $mentah * ($faktor[$b->pos_id] ?? 1)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.pagination :data="$data" />
    @endif
</x-layouts.admin>

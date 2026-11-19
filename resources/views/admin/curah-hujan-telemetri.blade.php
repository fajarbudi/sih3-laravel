@php
    // Seperti aplikasi lama (AnaRp2): 2 desimal, sel kosong bila tidak ada data.
    $angka = fn ($n) => $n === null ? '' : number_format($n, 2, ',', '.');
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2 py-2';
    $diubah = ($filter['walidata'] ?? null) || ($filter['pos'] ?? null) || $tanggal->toDateString() !== today()->toDateString();
    $kemarin = $tanggal->copy()->subDay();
@endphp

<x-layouts.admin judul="Curah Hujan Telemetri" induk="Hidrometeorologi" :penuh="true">
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
            {{-- Default hari ini seperti aplikasi lama. --}}
            <label for="tanggal" class="sr-only">Tanggal</label>
            <input id="tanggal" name="tanggal" type="date" required max="{{ today()->toDateString() }}" value="{{ $tanggal->toDateString() }}" onchange="this.form.requestSubmit()" class="{{ $pil }} w-40">
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($diubah)
                <a href="{{ route('admin.curah-hujan-telemetri') }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ $pos->count() }} pos</p>
        </form>
    </x-slot:filter>

    @if ($pos->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-cloud-rain text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">Tidak ada data telemetri untuk hari hujan ini</p>
            <p class="mt-1 text-gray-600">{{ $kemarin->translatedFormat('j F Y') }} pukul 07.00 s.d. {{ $tanggal->translatedFormat('j F Y') }} pukul 06.59.</p>
        </div>
    @else
        {{--
            Seperti aplikasi lama (varrtele.php): No., Pos (+ walidata), jam 07-23 hari sebelumnya, jam 00-06 tanggal terpilih, Total.
        --}}
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">Curah hujan telemetri per jam (mm), {{ $kemarin->translatedFormat('j F Y') }} 07.00 s.d. {{ $tanggal->translatedFormat('j F Y') }} 06.59</caption>
                <thead class="bg-gray-50 align-bottom text-gray-700">
                    <tr>
                        <th scope="col" rowspan="2" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" rowspan="2" class="{{ $sel }} font-medium">Pos</th>
                        <th scope="colgroup" colspan="17" class="{{ $sel }} text-center font-medium">{{ $kemarin->translatedFormat('l, j F Y') }}</th>
                        <th scope="colgroup" colspan="7" class="{{ $sel }} text-center font-medium">{{ $tanggal->translatedFormat('l, j F Y') }}</th>
                        <th scope="col" rowspan="2" class="{{ $sel }} text-right font-medium">Total</th>
                    </tr>
                    <tr>
                        @foreach ($daftarJam as $jam)
                            <th scope="col" class="{{ $sel }} text-center font-medium tabular-nums">{{ $jam->format('H') }}:00</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pos as $p)
                        @php($perJam = $rekap->get($p->id))
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $loop->iteration }}.</td>
                            <td class="{{ $sel }} min-w-40">
                                {{ $p->nama }}
                                <span class="block text-xs italic text-gray-600">{{ $p->walidata?->nama }}</span>
                            </td>
                            @foreach ($daftarJam as $jam)
                                <td class="{{ $sel }} text-right tabular-nums">{{ $angka($perJam->get($jam->format('Y-m-d H'))?->hujan) }}</td>
                            @endforeach
                            <td class="{{ $sel }} text-right font-medium tabular-nums">{{ $angka($perJam->sum('hujan')) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.admin>

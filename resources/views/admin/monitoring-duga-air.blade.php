@php
    $angka = fn ($n) => str_replace('.', ',', rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.'));
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2.5 py-2';
    $kemarin = today()->subDay()->toDateString();
    $diubah = ($filter['walidata'] ?? null) || ($filter['cari'] ?? null) || $tanggal->toDateString() !== $kemarin;
    $nilai = App\Http\Controllers\Admin\MonitoringDugaAirController::NILAI;
@endphp

<x-layouts.admin judul="Monitoring Duga Air" induk="Hidrologi" :penuh="true">
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
            {{-- Default kemarin seperti aplikasi lama. --}}
            <label for="tanggal" class="sr-only">Tanggal</label>
            <input id="tanggal" name="tanggal" type="date" required max="{{ today()->toDateString() }}" value="{{ $tanggal->toDateString() }}" onchange="this.form.requestSubmit()" class="{{ $pil }} w-40">
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($diubah)
                <a href="{{ route('admin.monitoring-duga-air') }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ $bacaan->count() }} dari {{ number_format($jumlahPos, 0, ',', '.') }} pos mengisi</p>
            <label for="cari" class="sr-only">Cari nama pos</label>
            <div class="relative ml-auto max-sm:w-full">
                <i class="bi bi-search pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-600" aria-hidden="true"></i>
                <input id="cari" name="cari" type="search" value="{{ $filter['cari'] ?? '' }}" placeholder="Cari nama pos" maxlength="100" onchange="this.form.requestSubmit()" class="{{ $pil }} w-full pl-7 sm:w-48">
            </div>
        </form>
    </x-slot:filter>

    @if ($pos->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-droplet text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">Tidak ada pos AWLR</p>
            <p class="mt-1 text-gray-600">{{ $filter ? 'Coba ubah atau hapus filter.' : 'Belum ada pos AWLR yang bisa Anda lihat.' }}</p>
        </div>
    @else
        {{--
            Seperti aplikasi lama (vmawlr.php): No., POS + walidata, lalu per jam 7/12/17 nilai limpas, intake kanan,
            intake kiri, dan rata-ratanya; "x" bila tidak ada; baris pos yang sudah mengisi berlatar hijau
            (status juga ditulis untuk pembaca layar, tidak hanya warna).
        --}}
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <p class="mb-3 text-gray-600">Pembacaan {{ $tanggal->translatedFormat('l, j F Y') }}. Baris hijau = pos sudah mengisi.</p>
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">Monitoring pengisian duga air {{ $tanggal->translatedFormat('j F Y') }}</caption>
                <thead class="bg-gray-50 align-bottom text-gray-700">
                    <tr>
                        <th scope="col" rowspan="2" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" rowspan="2" class="{{ $sel }} font-medium">POS</th>
                        @foreach (App\Http\Controllers\Admin\MonitoringDugaAirController::JAM as $jam)
                            <th scope="colgroup" colspan="4" class="{{ $sel }} text-center font-medium">Jam {{ sprintf('%02d.00', $jam) }} (m)</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach (App\Http\Controllers\Admin\MonitoringDugaAirController::JAM as $jam)
                            @foreach ($nilai as $teks)
                                <th scope="col" class="{{ $sel }} text-right font-medium">{{ $teks }}</th>
                            @endforeach
                            <th scope="col" class="{{ $sel }} text-right font-medium">Rata</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pos as $p)
                        @php($perJam = $bacaan->get($p->id))
                        <tr class="{{ $perJam ? 'bg-green-50' : 'hover:bg-gray-50' }}">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $pos->firstItem() + $loop->index }}.</td>
                            <td class="{{ $sel }} min-w-40">
                                {{ $p->nama }}
                                <span class="block text-xs text-gray-600">{{ $p->walidata?->nama }}</span>
                                <span class="sr-only">{{ $perJam ? 'sudah mengisi' : 'belum mengisi' }}</span>
                            </td>
                            @foreach (App\Http\Controllers\Admin\MonitoringDugaAirController::JAM as $jam)
                                @php($b = $perJam?->get($jam))
                                @php($ada = collect(array_keys($nilai))->map(fn ($k) => $b?->$k)->filter(fn ($v) => $v !== null))
                                @foreach ($nilai as $kolom => $teks)
                                    <td class="{{ $sel }} text-right tabular-nums {{ $b?->$kolom === null ? 'text-gray-600' : '' }}">{{ $b?->$kolom === null ? 'x' : $angka($b->$kolom) }}</td>
                                @endforeach
                                <td class="{{ $sel }} text-right font-medium tabular-nums {{ $ada->isEmpty() ? 'text-gray-600' : '' }}">{{ $ada->isEmpty() ? 'x' : number_format($ada->avg(), 2, ',', '') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.pagination :data="$pos" />
    @endif
</x-layouts.admin>

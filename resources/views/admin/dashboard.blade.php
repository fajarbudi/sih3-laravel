@php
    use App\Services\KondisiTerkini;

    $angka = fn ($n, $desimal = 0) => number_format($n, $desimal, ',', '.');
    $tanggal = fn ($t, $format = 'j M') => \Illuminate\Support\Carbon::parse($t)->translatedFormat($format);
    $adaHujanPerJam = collect($hujanPerJam)->sum('hujan') > 0;
    $hujanTertinggi = $hujanPerPos->first();

    // Tren kecil muka air (kanvas 64 x 20). Skala diberi rentang minimal 10 cm agar fluktuasi sensor tidak tampak seperti lonjakan.
    $jejak = function (array $nilai) {
        if (count($nilai) < 2) {
            return null;
        }
        $tengah = (min($nilai) + max($nilai)) / 2;
        $rentang = max(max($nilai) - min($nilai), 0.10);

        return collect($nilai)->values()->map(fn ($v, $i) => round($i / (count($nilai) - 1) * 64, 2).','.round(10 - ($v - $tengah) / $rentang * 16, 2))->implode(' ');
    };
@endphp

<x-layouts.admin judul="Halaman Depan" induk="Dashboard" :keterangan="'Diperbarui '.$sekarang->translatedFormat('j F Y, H:i').' WIB'">
    {{-- HUJAN: bagian utama --}}
    <section class="rounded-lg border border-gray-200">
        <header class="flex items-baseline justify-between gap-4 border-b border-gray-200 px-4 py-2.5">
            <h2 class="font-medium">Hujan</h2>
            <p class="text-gray-500">24 jam terakhir</p>
        </header>

        @if (! $hujanTertinggi)
            <p class="px-5 py-14 text-center text-gray-500">Tidak ada hujan tercatat dalam 24 jam terakhir.</p>
        @else
            <div class="grid lg:grid-cols-[18rem_1fr]">
                {{-- Angka utama + peringkat pos --}}
                <div class="min-w-0 border-gray-200 px-5 py-5 max-lg:border-b lg:border-r">
                    <p class="text-gray-500">Tertinggi</p>
                    <p class="mt-1 text-5xl font-semibold tracking-tight">{{ $angka($hujanTertinggi, 1) }}<span class="ml-1 text-xl font-normal text-gray-500">mm</span></p>
                    <p class="mt-2 font-medium">Hujan {{ Str::lower(KondisiTerkini::kategoriHujan($hujanTertinggi)) }}</p>
                    <p class="text-gray-500">{{ $hujanPerPos->keys()->first() }}</p>

                    <ol class="mt-6 space-y-2 border-t border-gray-100 pt-4">
                        @foreach ($hujanPerPos->take(5) as $nama => $mm)
                            <li class="flex items-baseline justify-between gap-3">
                                <span class="truncate">{{ $nama }}</span>
                                <span class="shrink-0 tabular-nums"><span class="font-medium">{{ $angka($mm, 1) }}</span> <span class="text-gray-500">mm</span></span>
                            </li>
                        @endforeach
                    </ol>
                    @if ($hujanPerPos->count() > 5)
                        <p class="mt-2 text-xs text-gray-500">{{ $hujanPerPos->count() - 5 }} pos lain mencatat hujan lebih kecil.</p>
                    @endif
                </div>

                {{-- Grafik per jam --}}
                <div class="flex min-w-0 flex-col px-3 py-4">
                    <p class="px-2 text-gray-500">Curah hujan per jam, 48 jam terakhir <span class="text-gray-400" aria-hidden="true">·</span> pos terderas tiap jam</p>
                    @if ($adaHujanPerJam)
                        <div class="relative mt-2 h-64">
                            <canvas id="grafik-hujan-jam" role="img" aria-label="Curah hujan per jam, 48 jam terakhir. Data lengkap ada di tabel di bawah grafik."></canvas>
                            <p class="grafik-gagal absolute inset-0 hidden items-center justify-center text-center text-gray-500">Grafik tidak dapat dimuat. Datanya tetap bisa dilihat di tabel di bawah.</p>
                        </div>
                        <details class="mt-2 px-2">
                            <summary class="flex min-h-11 items-center text-gray-500 hover:text-gray-900 lg:min-h-8">Lihat data per jam dalam tabel</summary>
                            <table class="mt-2 w-full text-left tabular-nums">
                                <thead class="text-gray-500"><tr><th scope="col" class="py-1.5 font-medium">Jam</th><th scope="col" class="py-1.5 text-right font-medium">Hujan (mm)</th><th scope="col" class="py-1.5 pl-4 font-medium">Pos</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach (collect($hujanPerJam)->where('hujan', '>', 0) as $j)
                                        <tr><td class="py-1.5">{{ $tanggal($j['jam'], 'j M, H:i') }}</td><td class="py-1.5 text-right">{{ $angka($j['hujan'], 1) }}</td><td class="py-1.5 pl-4">{{ $j['pos'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                    @else
                        <p class="flex flex-1 items-center justify-center py-10 text-gray-500">Tidak ada hujan tercatat dalam 48 jam terakhir.</p>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <div class="mt-4 grid items-start gap-4 lg:grid-cols-3">
        {{-- MUKA AIR: tabel --}}
        <section class="min-w-0 rounded-lg border border-gray-200 lg:col-span-2">
            <header class="flex items-baseline justify-between gap-4 border-b border-gray-200 px-4 py-2.5">
                <h2 class="font-medium">Muka air</h2>
                <p class="text-gray-500">{{ $posNaik ? $posNaik.' pos naik dalam 24 jam' : 'Tidak ada pos yang naik dalam 24 jam' }}</p>
            </header>
            @if ($mukaAir->isEmpty())
                <p class="px-5 py-14 text-center text-gray-500">Belum ada data muka air telemetri dalam 7 hari terakhir.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-gray-500">
                            <tr class="border-b border-gray-100">
                                <th scope="col" class="px-5 py-2 font-medium">Pos</th>
                                <th scope="col" class="px-3 py-2 text-right font-medium">Tinggi</th>
                                <th scope="col" class="px-3 py-2 font-medium">24 jam</th>
                                <th scope="col" class="px-3 py-2 font-medium max-sm:hidden"><span class="sr-only">Tren</span></th>
                                <th scope="col" class="px-5 py-2 text-right font-medium max-sm:hidden">Update</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($mukaAir as $m)
                                @php
                                    $p = $m['perubahan'];
                                    $titik = $jejak($m['jejak']);
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="max-w-40 truncate px-5 py-2.5 font-medium sm:max-w-56" title="{{ $m['nama'] }}">{{ $m['nama'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{{ $angka($m['tma'], 2) }} <span class="text-gray-500">m</span></td>
                                    <td class="whitespace-nowrap px-3 py-2.5 tabular-nums">
                                        @if ($p === null)
                                            <span class="text-gray-500">–</span>
                                        @elseif ($p >= $ambangPerubahan)
                                            <span class="font-medium">↑ {{ $angka($p * 100) }} cm</span>
                                        @elseif ($p <= -$ambangPerubahan)
                                            <span>↓ {{ $angka(abs($p) * 100) }} cm</span>
                                        @else
                                            <span class="text-gray-500">Stabil</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 max-sm:hidden">
                                        @if ($titik)
                                            <svg class="h-5 w-16" viewBox="0 0 64 20" aria-hidden="true"><polyline points="{{ $titik }}" fill="none" stroke="#2a78d6" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" /></svg>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-right text-gray-500 max-sm:hidden">{{ $m['waktu']->diffForHumans(short: true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-gray-100 px-5 py-2.5 text-xs text-gray-500">↑ naik / ↓ turun minimal {{ $angka($ambangPerubahan * 100) }} cm dibanding 24 jam sebelumnya. "–" berarti data pos belum 24 jam.</p>
            @endif
        </section>

        <div class="flex min-w-0 flex-col gap-4">
            {{-- CUACA --}}
            <section class="rounded-lg border border-gray-200">
                <header class="flex items-baseline justify-between gap-4 border-b border-gray-200 px-4 py-2.5">
                    <h2 class="font-medium">Cuaca</h2>
                    <p class="text-gray-500">Bacaan terakhir</p>
                </header>
                @forelse ($cuaca as $c)
                    <div class="border-b border-gray-100 px-5 py-4 last:border-b-0">
                        <p class="truncate text-gray-500">{{ $c->nama }}</p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight">{{ $c->suhu === null ? '–' : $angka((float) $c->suhu, 1) }}<span class="ml-0.5 text-base font-normal text-gray-500">°C</span></p>
                        <p class="mt-1 text-gray-600">
                            Kelembapan {{ $c->kelembapan === null ? '–' : $angka((float) $c->kelembapan).'%' }},
                            angin {{ $c->kecepatan_angin === null ? '–' : $angka((float) $c->kecepatan_angin, 1).' m/s' }}
                        </p>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-gray-500">Belum ada data klimatologi telemetri dalam 7 hari terakhir.</p>
                @endforelse
            </section>

            {{-- PERMINTAAN DATA --}}
            <section class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-5 py-4">
                <h2 class="font-medium">Permintaan data</h2>
                <p class="text-gray-600">{{ $jumlahMenunggu ? $angka($jumlahMenunggu).' menunggu persetujuan' : 'Tidak ada yang menunggu' }}</p>
            </section>
        </div>
    </div>

    @if ($adaHujanPerJam)
        @push('skrip')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    // Script grafik gagal dimuat: tampilkan pesan, tabel data tetap bisa dibuka.
                    if (typeof Chart === 'undefined') {
                        document.querySelectorAll('.grafik-gagal').forEach((el) => el.classList.replace('hidden', 'flex'));
                        return;
                    }

                    const hujanJam = @json($hujanPerJam);
                    const angka = (n, d = 0) => n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
                    // Label sumbu jam: setiap 6 jam (00, 06, 12, 18), tanggal ditulis pada pukul 00.
                    const labelJam = (jam) => {
                        const pukul = Number(jam.slice(11, 13));
                        if (pukul % 6 !== 0) return '';
                        const tgl = new Date(jam.slice(0, 10) + 'T00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                        return pukul === 0 ? [jam.slice(11, 16), tgl] : jam.slice(11, 16);
                    };

                    new Chart(document.getElementById('grafik-hujan-jam'), {
                        type: 'bar',
                        data: {
                            labels: hujanJam.map((j) => j.jam),
                            // Satu seri, satu warna: biru slot 1 palet dataviz.
                            datasets: [{ data: hujanJam.map((j) => j.hujan), backgroundColor: '#2a78d6', hoverBackgroundColor: '#1c5cab', maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' }],
                        },
                        options: {
                            maintainAspectRatio: false,
                            font: { family: "'Inter', sans-serif" },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#ffffff', borderColor: '#e5e7eb', borderWidth: 1, padding: 8, cornerRadius: 6, displayColors: false,
                                    titleColor: '#4a5565', titleFont: { family: "'Inter', sans-serif", weight: '500', size: 11 },
                                    bodyColor: '#111827', bodyFont: { family: "'Inter', sans-serif", weight: '600', size: 12 },
                                    callbacks: {
                                        title: (i) => new Date(hujanJam[i[0].dataIndex].jam.replace(' ', 'T')).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }),
                                        label: (i) => [`${angka(i.raw, 1)} mm`, hujanJam[i.dataIndex].pos ?? ''].filter(Boolean),
                                    },
                                },
                            },
                            scales: {
                                x: { grid: { display: false }, border: { color: '#d1d5dc' }, ticks: { color: '#6a7282', font: { family: "'Inter', sans-serif", size: 11 }, maxRotation: 0, autoSkip: false, callback: (v, i) => labelJam(hujanJam[i].jam) } },
                                y: { beginAtZero: true, grid: { color: '#f0f1f3', drawTicks: false }, border: { display: false }, ticks: { color: '#6a7282', font: { family: "'Inter', sans-serif", size: 11 }, padding: 8, callback: (v) => angka(v, Number.isInteger(v) ? 0 : 1) } },
                            },
                        },
                    });
                });
            </script>
        @endpush
    @endif
</x-layouts.admin>

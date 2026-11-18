@php
    $angka = fn ($n, $desimal = 0) => number_format($n, $desimal, ',', '.');
    $tanggal = fn ($t, $format = 'j M') => \Illuminate\Support\Carbon::parse($t)->translatedFormat($format);
    $adaHujanPerJam = collect($hujanPerJam)->sum('hujan') > 0;
    $adaBacaan = collect($bacaanPerHari['seri'])->flatten()->sum() > 0;
@endphp

<x-layouts.admin judul="Dashboard" :keterangan="'Kondisi per '.$sekarang->translatedFormat('j F Y, H:i').' WIB'">
    {{-- Angka ringkas: hanya yang tidak perlu grafik --}}
    <dl class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-gray-200 px-4 py-4">
            <dt class="text-gray-500">Perangkat telemetri aktif</dt>
            <dd class="mt-1.5 text-2xl font-semibold tracking-tight">{{ $angka($perangkatAktif) }} <span class="text-sm font-normal text-gray-500">dari {{ $angka($jumlahPerangkat) }}</span></dd>
            <dd class="mt-0.5 text-gray-500">Mengirim data dalam {{ $batasDiamJam }} jam terakhir</dd>
        </div>
        <div class="rounded-lg border border-gray-200 px-4 py-4">
            <dt class="text-gray-500">Permintaan data menunggu</dt>
            <dd class="mt-1.5 text-2xl font-semibold tracking-tight">{{ $angka($jumlahMenunggu) }}</dd>
            <dd class="mt-0.5 text-gray-500">{{ $jumlahMenunggu ? 'Perlu disetujui atau ditolak' : 'Tidak ada antrean' }}</dd>
        </div>
        @if ($buletinDraf !== null)
            <div class="rounded-lg border border-gray-200 px-4 py-4">
                <dt class="text-gray-500">Buletin BMKG belum terbit</dt>
                <dd class="mt-1.5 text-2xl font-semibold tracking-tight">{{ $angka($buletinDraf) }}</dd>
                <dd class="mt-0.5 text-gray-500">Draf otomatis, perlu ditinjau</dd>
            </div>
        @endif
    </dl>

    {{-- Hujan --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <x-admin.grafik id="grafik-hujan-jam" class="xl:col-span-2"
            judul="Kapan hujan turun dalam 48 jam terakhir?"
            keterangan="Curah hujan per jam (mm) di pos yang paling deras pada jam itu."
            :kosong="! $adaHujanPerJam" kosong-teks="Tidak ada hujan tercatat dalam 48 jam terakhir.">
            <x-slot:tabel>
                <thead class="text-gray-600"><tr><th scope="col" class="py-2 font-medium">Jam</th><th scope="col" class="py-2 text-right font-medium">Hujan (mm)</th><th scope="col" class="py-2 pl-4 font-medium">Pos</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach (collect($hujanPerJam)->where('hujan', '>', 0) as $j)
                        <tr><td class="py-1.5">{{ $tanggal($j['jam'], 'j M, H:i') }}</td><td class="py-1.5 text-right">{{ $angka($j['hujan'], 1) }}</td><td class="py-1.5 pl-4">{{ $j['pos'] }}</td></tr>
                    @endforeach
                </tbody>
            </x-slot:tabel>
        </x-admin.grafik>

        <x-admin.grafik id="grafik-hujan-pos" tinggi="h-80"
            judul="Pos mana yang paling basah?"
            keterangan="Total curah hujan 24 jam terakhir (mm)."
            :kosong="$hujanPerPos->isEmpty()" kosong-teks="Tidak ada hujan tercatat dalam 24 jam terakhir.">
            <x-slot:tabel>
                <thead class="text-gray-600"><tr><th scope="col" class="py-2 font-medium">Pos</th><th scope="col" class="py-2 text-right font-medium">Hujan (mm)</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($hujanPerPos as $nama => $mm)
                        <tr><td class="py-1.5">{{ $nama }}</td><td class="py-1.5 text-right">{{ $angka($mm, 1) }}</td></tr>
                    @endforeach
                </tbody>
            </x-slot:tabel>
        </x-admin.grafik>
    </div>

    {{-- Kesehatan data --}}
    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <x-admin.grafik id="grafik-bacaan" class="xl:col-span-2"
            judul="Apakah data masuk normal setiap hari?"
            keterangan="Jumlah bacaan telemetri tersimpan per hari, 14 hari terakhir. Hari ini masih berjalan."
            :kosong="! $adaBacaan" kosong-teks="Belum ada bacaan telemetri dalam 14 hari terakhir.">
            <x-slot:tabel>
                <thead class="text-gray-600">
                    <tr>
                        <th scope="col" class="py-2 font-medium">Tanggal</th>
                        @foreach (array_keys($bacaanPerHari['seri']) as $nama)
                            <th scope="col" class="py-2 text-right font-medium">{{ $nama }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($bacaanPerHari['tanggal'] as $i => $t)
                        <tr>
                            <td class="py-1.5">{{ $tanggal($t) }}</td>
                            @foreach ($bacaanPerHari['seri'] as $seri)
                                <td class="py-1.5 text-right">{{ $angka($seri[$i]) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </x-slot:tabel>
        </x-admin.grafik>

        <div class="flex flex-col gap-4">
            {{-- Daftar meter: satu baris per sumber, isi batang = bagian perangkat yang aktif --}}
            <section class="rounded-lg border border-gray-200 bg-white">
                <header class="px-4 pt-4">
                    <h2 class="font-medium">Sumber mana yang bermasalah?</h2>
                    <p class="mt-0.5 text-gray-500">Perangkat yang mengirim data dalam {{ $batasDiamJam }} jam terakhir.</p>
                </header>
                <ul class="space-y-3.5 px-4 py-4">
                    @forelse ($statusSumber as $sumber => $s)
                        @php
                            $bagian = $s['jumlah'] ? $s['aktif'] / $s['jumlah'] : 0;
                            [$warna, $jalur, $status, $ikon] = match (true) {
                                $bagian >= 1 => ['#0ca30c', '#dcf5dc', 'Semua aktif', 'M4.5 12.75l6 6 9-13.5'],
                                $bagian > 0 => ['#fab219', '#fdf0d0', 'Sebagian diam', 'M12 9v3.75m0 3.75h.008v.008H12v-.008z'],
                                default => ['#d03b3b', '#f8dcdc', 'Tidak ada data baru', 'M6 18L18 6M6 6l12 12'],
                            };
                        @endphp
                        <li>
                            <div class="flex items-baseline justify-between gap-4">
                                <span class="font-medium">{{ $sumber }}</span>
                                <span class="shrink-0 tabular-nums text-gray-600">{{ $s['aktif'] }}/{{ $s['jumlah'] }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full" style="background: {{ $jalur }}" role="meter" aria-valuemin="0" aria-valuemax="{{ $s['jumlah'] }}" aria-valuenow="{{ $s['aktif'] }}" aria-label="{{ $sumber }}: {{ $s['aktif'] }} dari {{ $s['jumlah'] }} perangkat aktif">
                                <div class="h-1.5 rounded-full" style="width: {{ $bagian * 100 }}%; background: {{ $warna }}"></div>
                            </div>
                            <p class="mt-1 flex items-center gap-1 text-xs text-gray-600">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="{{ $warna }}" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon }}"/></svg>
                                {{ $status }}
                            </p>
                        </li>
                    @empty
                        <li class="py-6 text-center text-gray-500">Belum ada perangkat telemetri yang terdaftar.</li>
                    @endforelse
                </ul>
            </section>

            <x-admin.grafik id="grafik-pos-jenis" tinggi="h-56"
                judul="Berapa pos per jenis?"
                :keterangan="$angka($posPerJenis->sum()).' pos pengamatan.'"
                :kosong="$posPerJenis->isEmpty()" kosong-teks="Belum ada pos.">
                <x-slot:tabel>
                    <thead class="text-gray-600"><tr><th scope="col" class="py-2 font-medium">Jenis</th><th scope="col" class="py-2 text-right font-medium">Pos</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($posPerJenis as $jenis => $jumlah)
                            <tr><td class="py-1.5">{{ $jenis }}</td><td class="py-1.5 text-right">{{ $angka($jumlah) }}</td></tr>
                        @endforeach
                    </tbody>
                </x-slot:tabel>
            </x-admin.grafik>
        </div>
    </div>

    @push('skrip')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // Script grafik gagal dimuat: tampilkan pesan, tabel data tetap bisa dibuka.
                if (typeof Chart === 'undefined') {
                    document.querySelectorAll('.grafik-gagal').forEach((el) => el.classList.replace('hidden', 'flex'));
                    return;
                }

                const SERI = ['#2a78d6', '#eb6834', '#1baf7a']; // palet dataviz slot 1-3, divalidasi untuk latar putih
                const TINTA = { utama: '#0b0b0b', kedua: '#52514e', sumbu: '#6a7282', grid: '#e1e0d9', dasar: '#c3c2b7' };
                const angka = (n, d = 0) => n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
                const angkaSumbu = (v) => angka(v, Number.isInteger(v) ? 0 : 1);
                // Label sumbu jam: setiap 6 jam (00, 06, 12, 18), tanggal ditulis pada pukul 00.
                const labelJam = (jam) => {
                    const pukul = Number(jam.slice(11, 13));
                    if (pukul % 6 !== 0) return '';
                    const tgl = new Date(jam.slice(0, 10) + 'T00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                    return pukul === 0 ? [jam.slice(11, 16), tgl] : jam.slice(11, 16);
                };

                Chart.defaults.font.family = "'Inter', sans-serif";
                Chart.defaults.font.size = 11;
                Chart.defaults.color = TINTA.sumbu;
                Chart.defaults.maintainAspectRatio = false;
                Object.assign(Chart.defaults.plugins.tooltip, {
                    backgroundColor: '#ffffff', borderColor: 'rgba(11,11,11,0.10)', borderWidth: 1,
                    titleColor: TINTA.kedua, titleFont: { weight: '500' }, bodyColor: TINTA.utama, bodyFont: { weight: '600' },
                    padding: 10, cornerRadius: 8, boxWidth: 12, boxHeight: 2, boxPadding: 6,
                });

                const sumbu = (opsi = {}) => ({
                    grid: { color: TINTA.grid, lineWidth: 1, drawTicks: false },
                    border: { display: false },
                    ticks: { padding: 8 },
                    ...opsi,
                });
                const tanpaGrid = (opsi = {}) => ({ grid: { display: false }, border: { color: TINTA.dasar }, ticks: { padding: 8 }, ...opsi });
                const batang = { maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' };

                // Nilai di ujung batang horizontal (label langsung, teks memakai tinta, bukan warna seri).
                const labelUjung = (desimal) => ({
                    id: 'labelUjung',
                    afterDatasetsDraw(chart) {
                        const { ctx } = chart;
                        ctx.save();
                        ctx.font = "500 11px 'Inter', sans-serif";
                        ctx.fillStyle = TINTA.utama;
                        ctx.textBaseline = 'middle';
                        chart.getDatasetMeta(0).data.forEach((bar, i) => ctx.fillText(angka(chart.data.datasets[0].data[i], desimal), bar.x + 6, bar.y));
                        ctx.restore();
                    },
                });

                // Garis bantu vertikal yang mengikuti titik data terdekat (grafik garis).
                const crosshair = {
                    id: 'crosshair',
                    afterDatasetsDraw(chart) {
                        const aktif = chart.tooltip?.getActiveElements();
                        if (!aktif?.length) return;
                        const { ctx, chartArea } = chart;
                        ctx.save();
                        ctx.strokeStyle = TINTA.dasar;
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(aktif[0].element.x, chartArea.top);
                        ctx.lineTo(aktif[0].element.x, chartArea.bottom);
                        ctx.stroke();
                        ctx.restore();
                    },
                };

                const el = (id) => document.getElementById(id);

                @if ($adaHujanPerJam)
                    const hujanJam = @json($hujanPerJam);
                    new Chart(el('grafik-hujan-jam'), {
                        type: 'bar',
                        data: {
                            labels: hujanJam.map((j) => j.jam),
                            datasets: [{ data: hujanJam.map((j) => j.hujan), backgroundColor: SERI[0], hoverBackgroundColor: '#1c5cab', ...batang }],
                        },
                        options: {
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        title: (i) => new Date(hujanJam[i[0].dataIndex].jam.replace(' ', 'T')).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }),
                                        label: (i) => `${angka(i.raw, 1)} mm` + (hujanJam[i.dataIndex].pos ? `  ${hujanJam[i.dataIndex].pos}` : ''),
                                    },
                                },
                            },
                            scales: {
                                x: tanpaGrid({ ticks: { padding: 8, maxRotation: 0, autoSkip: false, callback: (v, i) => labelJam(hujanJam[i].jam) } }),
                                y: sumbu({ beginAtZero: true, ticks: { padding: 8, callback: angkaSumbu } }),
                            },
                        },
                    });
                @endif

                @if ($hujanPerPos->isNotEmpty())
                    new Chart(el('grafik-hujan-pos'), {
                        type: 'bar',
                        data: {
                            labels: @json($hujanPerPos->keys()),
                            datasets: [{ data: @json($hujanPerPos->values()), backgroundColor: SERI[0], hoverBackgroundColor: '#1c5cab', ...batang }],
                        },
                        plugins: [labelUjung(1)],
                        options: {
                            indexAxis: 'y',
                            layout: { padding: { right: 40 } },
                            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (i) => `${angka(i.raw, 1)} mm` } } },
                            scales: { x: sumbu({ beginAtZero: true, display: false }), y: tanpaGrid({ border: { display: false } }) },
                        },
                    });
                @endif

                @if ($adaBacaan)
                    const bacaan = @json($bacaanPerHari);
                    new Chart(el('grafik-bacaan'), {
                        type: 'line',
                        data: {
                            labels: bacaan.tanggal.map((t) => new Date(t).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })),
                            datasets: Object.entries(bacaan.seri).map(([nama, data], i) => ({
                                label: nama, data, borderColor: SERI[i], backgroundColor: SERI[i] + '1a', fill: true,
                                borderWidth: 2, tension: 0.25, pointRadius: 0, pointHoverRadius: 4,
                                pointHoverBorderColor: '#ffffff', pointHoverBorderWidth: 2, pointHoverBackgroundColor: SERI[i],
                            })),
                        },
                        plugins: [crosshair],
                        options: {
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top', align: 'start', labels: { usePointStyle: true, pointStyle: 'line', boxWidth: 16, color: TINTA.kedua, padding: 16 } },
                                tooltip: { usePointStyle: true, callbacks: { label: (i) => `${angka(i.raw)}  ${i.dataset.label}` } },
                            },
                            scales: { x: tanpaGrid(), y: sumbu({ beginAtZero: true, ticks: { padding: 8, callback: angkaSumbu } }) },
                        },
                    });
                @endif

                @if ($posPerJenis->isNotEmpty())
                    new Chart(el('grafik-pos-jenis'), {
                        type: 'bar',
                        data: {
                            labels: @json($posPerJenis->keys()),
                            datasets: [{ data: @json($posPerJenis->values()), backgroundColor: SERI[0], hoverBackgroundColor: '#1c5cab', ...batang }],
                        },
                        plugins: [labelUjung(0)],
                        options: {
                            indexAxis: 'y',
                            layout: { padding: { right: 36 } },
                            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (i) => `${angka(i.raw)} pos` } } },
                            scales: { x: sumbu({ beginAtZero: true, display: false }), y: tanpaGrid({ border: { display: false } }) },
                        },
                    });
                @endif
            });
        </script>
    @endpush
</x-layouts.admin>

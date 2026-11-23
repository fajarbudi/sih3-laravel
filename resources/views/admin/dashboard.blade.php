{{--
    Dashboard admin (DashboardController). Tugas layar: dalam sekali lihat, pengelola tahu (1) apakah ada pos yang
    perlu ditindaklanjuti (siaga, hujan lebat, telemetri mati, data manual belum diisi) lalu (2) detail kondisinya.
    Susunan (antislop, dial ENERGY 1 / RHYTHM 2 / MOTION 1, gaya panel admin):
    - Ringkasan: 4 ubin berisi angka asli, masing-masing tautan ke bagian detailnya. Warna hanya untuk keadaan yang
      perlu perhatian (siaga = kuning/merah, telemetri mati = merah); keadaan normal tetap netral.
    - Hujan: bagian utama (angka tertinggi, peringkat, grafik per jam 48 jam).
    - Kiri: muka air (10 pos dengan perubahan terbesar, sisanya lewat tombol). Kanan: pos siaga (bila ada),
      kesehatan telemetri per sumber, pengisian manual kemarin; cuaca & permintaan data di bawah muka air.
--}}
@php
    use App\Services\KondisiTerkini;

    $angka = fn ($n, $desimal = 0) => number_format($n, $desimal, ',', '.');
    $tanggal = fn ($t, $format = 'j M') => \Illuminate\Support\Carbon::parse($t)->translatedFormat($format);
    $adaHujanPerJam = collect($hujanPerJam)->sum('hujan') > 0;
    $hujanTertinggi = $hujanPerPos->first();
    $levelTertinggi = $siaga->max('level');
    $manualIsi = collect($manual)->sum('isi');
    $manualTotal = collect($manual)->sum('total');
    $telemetriMati = $telemetri['total'] - $telemetri['online'];
    $mukaAirTampil = 10;

    // Tren kecil muka air (kanvas 64 x 20). Skala diberi rentang minimal 10 cm agar fluktuasi sensor tidak tampak seperti lonjakan.
    $jejak = function (array $nilai) {
        if (count($nilai) < 2) {
            return null;
        }
        $tengah = (min($nilai) + max($nilai)) / 2;
        $rentang = max(max($nilai) - min($nilai), 0.10);

        return collect($nilai)->values()->map(fn ($v, $i) => round($i / (count($nilai) - 1) * 64, 2).','.round(10 - ($v - $tengah) / $rentang * 16, 2))->implode(' ');
    };

    // Gaya bersama: kartu bagian + kepala bagian berikon.
    $kartu = 'rounded-lg border border-gray-200 bg-white';
    $kepala = 'flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-2.5';
    $ubin = 'group flex min-h-28 flex-col rounded-lg border p-4 transition-colors hover:border-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600';
    // Warna level siaga: 2 Waspada kuning, 3 Siaga jingga, 4 Awas merah (sama dengan urutan legenda peta portal).
    $warnaSiaga = [2 => 'bg-yellow-100 text-yellow-900', 3 => 'bg-orange-100 text-orange-900', 4 => 'bg-red-100 text-red-900'];
@endphp

<x-layouts.admin judul="Halaman Depan" induk="Dashboard" :keterangan="'Diperbarui '.$sekarang->translatedFormat('j F Y, H:i').' WIB'">
    {{-- RINGKASAN --}}
    <h2 class="sr-only">Ringkasan</h2>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {{-- Status siaga --}}
        <a href="{{ $siaga->isNotEmpty() ? '#siaga' : '#muka-air' }}"
            class="{{ $ubin }} {{ $levelTertinggi >= 4 ? 'border-red-300 bg-red-50' : ($levelTertinggi ? 'border-orange-300 bg-orange-50' : 'border-gray-200 bg-white') }}">
            <span class="flex items-center gap-2 text-gray-600">
                <i class="bi {{ $siaga->isNotEmpty() ? 'bi-exclamation-triangle-fill text-orange-600' : 'bi-shield-check text-green-700' }}" aria-hidden="true"></i>Status siaga duga air
            </span>
            @if ($siaga->isNotEmpty())
                <span class="mt-2 text-2xl font-semibold tracking-tight text-gray-950">{{ $siaga->count() }} pos <span class="text-base font-medium">di atas batas</span></span>
                <span class="mt-auto pt-1 text-gray-700">{{ $siaga->countBy('label')->map(fn ($n, $l) => "$l $n")->join(' · ') }}</span>
            @else
                <span class="mt-2 text-2xl font-semibold tracking-tight text-gray-950">Normal</span>
                <span class="mt-auto pt-1 text-gray-600">Tidak ada pos melewati batas siaga</span>
            @endif
        </a>

        {{-- Hujan tertinggi --}}
        <a href="#hujan" class="{{ $ubin }} border-gray-200 bg-white">
            <span class="flex items-center gap-2 text-gray-600"><i class="bi bi-cloud-rain text-primary-600" aria-hidden="true"></i>Hujan tertinggi, 24 jam</span>
            @if ($hujanTertinggi)
                <span class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-gray-950">{{ $angka($hujanTertinggi, 1) }} <span class="text-base font-medium text-gray-600">mm</span></span>
                <span class="mt-auto truncate pt-1 text-gray-600">{{ KondisiTerkini::kategoriHujan($hujanTertinggi) }} · {{ $hujanPerPos->keys()->first() }}</span>
            @else
                <span class="mt-2 text-2xl font-semibold tracking-tight text-gray-950">Tidak hujan</span>
                <span class="mt-auto pt-1 text-gray-600">Tidak ada hujan tercatat telemetri</span>
            @endif
        </a>

        {{-- Telemetri --}}
        <a href="#telemetri" class="{{ $ubin }} border-gray-200 bg-white">
            <span class="flex items-center gap-2 text-gray-600"><i class="bi bi-broadcast text-primary-600" aria-hidden="true"></i>Telemetri mengirim data</span>
            @if ($telemetri['total'])
                <span class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-gray-950">{{ $telemetri['online'] }}<span class="text-base font-medium text-gray-600"> / {{ $telemetri['total'] }} pos</span></span>
                <span class="mt-auto block pt-2">
                    <span class="flex h-1.5 overflow-hidden rounded-full bg-red-200" aria-hidden="true"><span class="bg-green-600" style="width: {{ round($telemetri['online'] / $telemetri['total'] * 100) }}%"></span></span>
                    <span class="mt-1.5 block {{ $telemetriMati ? 'text-red-700' : 'text-gray-600' }}">{{ $telemetriMati ? $telemetriMati.' pos tidak mengirim data lebih dari 24 jam' : 'Semua pos mengirim data' }}</span>
                </span>
            @else
                <span class="mt-2 text-2xl font-semibold tracking-tight text-gray-950">–</span>
                <span class="mt-auto pt-1 text-gray-600">Tidak ada pos telemetri</span>
            @endif
        </a>

        {{-- Data manual kemarin --}}
        <a href="#manual" class="{{ $ubin }} border-gray-200 bg-white">
            <span class="flex items-center gap-2 text-gray-600"><i class="bi bi-clipboard-check text-primary-600" aria-hidden="true"></i>Data manual kemarin</span>
            @if ($manualTotal)
                <span class="mt-2 text-2xl font-semibold tracking-tight tabular-nums text-gray-950">{{ $manualIsi }}<span class="text-base font-medium text-gray-600"> / {{ $angka($manualTotal) }} pos diisi</span></span>
                <span class="mt-auto truncate pt-1 text-gray-600">{{ collect($manual)->map(fn ($m) => $m['label'].' '.$m['isi'].'/'.$m['total'])->join(' · ') }}</span>
            @else
                <span class="mt-2 text-2xl font-semibold tracking-tight text-gray-950">–</span>
                <span class="mt-auto pt-1 text-gray-600">Tidak ada pos pengamatan manual</span>
            @endif
        </a>
    </div>

    {{-- HUJAN: bagian utama --}}
    <section id="hujan" aria-labelledby="judul-hujan" class="mt-4 scroll-mt-4 {{ $kartu }}">
        <header class="{{ $kepala }}">
            <h2 id="judul-hujan" class="flex items-center gap-2 font-medium"><i class="bi bi-cloud-rain text-gray-500" aria-hidden="true"></i>Hujan</h2>
            <p class="text-gray-600">24 jam terakhir</p>
        </header>
        @if (! $hujanTertinggi)
            <p class="px-5 py-14 text-center text-gray-600">Tidak ada hujan tercatat dalam 24 jam terakhir.</p>
        @else
            <div class="grid lg:grid-cols-[18rem_1fr]">
                {{-- Angka utama + peringkat pos --}}
                <div class="min-w-0 border-gray-200 px-5 py-5 max-lg:border-b lg:border-r">
                    <p class="text-gray-600">Tertinggi</p>
                    <p class="mt-1 text-5xl font-semibold tracking-tight">{{ $angka($hujanTertinggi, 1) }}<span class="ml-1 text-xl font-normal text-gray-600">mm</span></p>
                    <p class="mt-2 font-medium">Hujan {{ Str::lower(KondisiTerkini::kategoriHujan($hujanTertinggi)) }}</p>
                    <p class="text-gray-600">{{ $hujanPerPos->keys()->first() }}</p>

                    <ol class="mt-6 space-y-2 border-t border-gray-100 pt-4">
                        @foreach ($hujanPerPos->take(5) as $nama => $mm)
                            <li class="flex items-baseline justify-between gap-3">
                                <span class="truncate">{{ $nama }}</span>
                                <span class="shrink-0 tabular-nums"><span class="font-medium">{{ $angka($mm, 1) }}</span> <span class="text-gray-600">mm</span></span>
                            </li>
                        @endforeach
                    </ol>
                    @if ($hujanPerPos->count() > 5)
                        <p class="mt-2 text-xs text-gray-600">{{ $hujanPerPos->count() - 5 }} pos lain mencatat hujan lebih kecil.</p>
                    @endif
                </div>

                {{-- Grafik per jam --}}
                <div class="flex min-w-0 flex-col px-3 py-4">
                    <p class="px-2 text-gray-600">Curah hujan per jam, 48 jam terakhir <span class="text-gray-400" aria-hidden="true">·</span> pos terderas tiap jam</p>
                    @if ($adaHujanPerJam)
                        <div class="relative mt-2 h-64">
                            <canvas id="grafik-hujan-jam" role="img" aria-label="Curah hujan per jam, 48 jam terakhir. Data lengkap ada di tabel di bawah grafik."></canvas>
                            <p class="grafik-gagal absolute inset-0 hidden items-center justify-center text-center text-gray-600">Grafik tidak dapat dimuat. Datanya tetap bisa dilihat di tabel di bawah.</p>
                        </div>
                        <details class="mt-2 px-2">
                            <summary class="flex min-h-11 items-center text-gray-600 hover:text-gray-900 lg:min-h-8">Lihat data per jam dalam tabel</summary>
                            <table class="mt-2 w-full text-left tabular-nums">
                                <thead class="text-gray-600"><tr><th scope="col" class="py-1.5 font-medium">Jam</th><th scope="col" class="py-1.5 text-right font-medium">Hujan (mm)</th><th scope="col" class="py-1.5 pl-4 font-medium">Pos</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach (collect($hujanPerJam)->where('hujan', '>', 0) as $j)
                                        <tr><td class="py-1.5">{{ $tanggal($j['jam'], 'j M, H:i') }}</td><td class="py-1.5 text-right">{{ $angka($j['hujan'], 1) }}</td><td class="py-1.5 pl-4">{{ $j['pos'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </details>
                    @else
                        <p class="flex flex-1 items-center justify-center py-10 text-gray-600">Tidak ada hujan tercatat dalam 48 jam terakhir.</p>
                    @endif
                </div>
            </div>
        @endif
    </section>

    <div class="mt-4 grid items-start gap-4 lg:grid-cols-3">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2">
        {{-- MUKA AIR --}}
        <section id="muka-air" aria-labelledby="judul-muka-air" class="min-w-0 scroll-mt-4 {{ $kartu }}">
            <header class="{{ $kepala }}">
                <h2 id="judul-muka-air" class="flex items-center gap-2 font-medium"><i class="bi bi-water text-gray-500" aria-hidden="true"></i>Muka air</h2>
                <p class="text-gray-600">{{ $posNaik ? $posNaik.' pos naik dalam 24 jam' : 'Tidak ada pos yang naik dalam 24 jam' }}</p>
            </header>
            @if ($mukaAir->isEmpty())
                <p class="px-5 py-14 text-center text-gray-600">Belum ada data muka air telemetri dalam 7 hari terakhir.</p>
            @else
                <div class="relative overflow-x-auto">
                    <table class="w-full text-left">
                        <caption class="sr-only">Muka air terkini per pos, perubahan terbesar dulu</caption>
                        <thead class="text-gray-600">
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
                                <tr class="hover:bg-gray-50" @if ($loop->iteration > $mukaAirTampil) hidden data-lainnya @endif>
                                    <td class="max-w-40 truncate px-5 py-2.5 font-medium sm:max-w-56" title="{{ $m['nama'] }}">{{ $m['nama'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums">{{ $angka($m['tma'], 2) }} <span class="text-gray-600">m</span></td>
                                    <td class="whitespace-nowrap px-3 py-2.5 tabular-nums">
                                        @if ($p === null)
                                            <span class="text-gray-600">–</span>
                                        @elseif ($p >= $ambangPerubahan)
                                            <span class="inline-flex items-center gap-1 rounded bg-primary-50 px-1.5 py-0.5 font-medium text-primary-800"><i class="bi bi-arrow-up-short" aria-hidden="true"></i><span class="sr-only">naik </span>{{ $angka($p * 100) }} cm</span>
                                        @elseif ($p <= -$ambangPerubahan)
                                            <span class="inline-flex items-center gap-1 text-gray-700"><i class="bi bi-arrow-down-short" aria-hidden="true"></i><span class="sr-only">turun </span>{{ $angka(abs($p) * 100) }} cm</span>
                                        @else
                                            <span class="text-gray-600">Stabil</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 max-sm:hidden">
                                        @if ($titik)
                                            <svg class="h-5 w-16" viewBox="0 0 64 20" aria-hidden="true"><polyline points="{{ $titik }}" fill="none" stroke="#2a78d6" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" /></svg>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-right text-gray-600 max-sm:hidden">{{ $m['waktu']->diffForHumans(short: true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-5 py-2">
                    <p class="text-xs text-gray-600">Naik/turun minimal {{ $angka($ambangPerubahan * 100) }} cm dibanding 24 jam sebelumnya. "–" berarti data pos belum 24 jam.</p>
                    @if ($mukaAir->count() > $mukaAirTampil)
                        <button type="button" id="tampil-muka-air" aria-expanded="false" class="flex min-h-11 items-center gap-1 rounded-md px-2 font-medium text-primary-700 hover:bg-primary-50 lg:min-h-8">
                            Tampilkan {{ $mukaAir->count() - $mukaAirTampil }} pos lainnya<i class="bi bi-chevron-down text-xs" aria-hidden="true"></i>
                        </button>
                    @endif
                </div>
            @endif
        </section>

            {{-- Cuaca + permintaan data, di bawah muka air agar kedua kolom seimbang --}}
            <div class="grid items-start gap-4 md:grid-cols-2">
                    {{-- CUACA --}}
                    <section aria-labelledby="judul-cuaca" class="min-w-0 {{ $kartu }}">
                        <header class="{{ $kepala }}">
                            <h2 id="judul-cuaca" class="flex items-center gap-2 font-medium"><i class="bi bi-thermometer-half text-gray-500" aria-hidden="true"></i>Cuaca</h2>
                            <p class="text-gray-600">Bacaan terakhir</p>
                        </header>
                        @forelse ($cuaca as $c)
                            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-4 py-3 last:border-b-0">
                                <div class="min-w-0">
                                    <p class="truncate text-gray-600" title="{{ $c->nama }}">{{ $c->nama }}</p>
                                    <p class="mt-0.5 text-gray-700">
                                        <i class="bi bi-droplet text-gray-500" aria-hidden="true"></i> {{ $c->kelembapan === null ? '–' : $angka((float) $c->kelembapan).'%' }}
                                        <span class="ml-2"><i class="bi bi-wind text-gray-500" aria-hidden="true"></i> {{ $c->kecepatan_angin === null ? '–' : $angka((float) $c->kecepatan_angin, 1).' m/s' }}</span>
                                    </p>
                                </div>
                                <p class="shrink-0 text-2xl font-semibold tracking-tight tabular-nums">{{ $c->suhu === null ? '–' : $angka((float) $c->suhu, 1) }}<span class="ml-0.5 text-sm font-normal text-gray-600">°C</span></p>
                            </div>
                        @empty
                            <p class="px-5 py-8 text-center text-gray-600">Belum ada data klimatologi telemetri dalam 7 hari terakhir.</p>
                        @endforelse
                    </section>

                    {{-- PERMINTAAN DATA --}}
                    <section aria-labelledby="judul-permintaan" class="flex min-w-0 flex-wrap items-center justify-between gap-x-4 gap-y-1 {{ $kartu }} px-4 py-3">
                        <h2 id="judul-permintaan" class="flex items-center gap-2 font-medium"><i class="bi bi-inbox text-gray-500" aria-hidden="true"></i>Permintaan data</h2>
                        @if ($jumlahMenunggu && Route::has('admin.unduh.verifikasi') && Gate::allows('admin'))
                            <a href="{{ route('admin.unduh.verifikasi') }}" class="font-medium text-primary-700 hover:underline">{{ $angka($jumlahMenunggu) }} menunggu persetujuan</a>
                        @else
                            <p class="text-gray-600">{{ $jumlahMenunggu ? $angka($jumlahMenunggu).' menunggu persetujuan' : 'Tidak ada yang menunggu' }}</p>
                        @endif
                    </section>
            </div>
        </div>

        <div class="flex min-w-0 flex-col gap-4">
            {{-- POS SIAGA (hanya bila ada) --}}
            @if ($siaga->isNotEmpty())
                <section id="siaga" aria-labelledby="judul-siaga" class="scroll-mt-4 rounded-lg border border-orange-300 bg-white">
                    <header class="{{ $kepala }} border-orange-200 bg-orange-50">
                        <h2 id="judul-siaga" class="flex items-center gap-2 font-medium"><i class="bi bi-exclamation-triangle-fill text-orange-600" aria-hidden="true"></i>Pos di atas batas siaga</h2>
                        <p class="text-gray-700">{{ $siaga->count() }} pos</p>
                    </header>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($siaga as $s)
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ $s['nama'] }}</span>
                                    <span class="text-gray-600">{{ $angka($s['nilai'], 2) }} m · {{ $s['waktu']?->diffForHumans(short: true) }}</span>
                                </span>
                                <span class="shrink-0 rounded px-2 py-0.5 text-xs font-semibold {{ $warnaSiaga[$s['level']] ?? $warnaSiaga[4] }}">{{ $s['label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- KESEHATAN TELEMETRI --}}
            <section id="telemetri" aria-labelledby="judul-telemetri" class="scroll-mt-4 {{ $kartu }}">
                <header class="{{ $kepala }}">
                    <h2 id="judul-telemetri" class="flex items-center gap-2 font-medium"><i class="bi bi-broadcast text-gray-500" aria-hidden="true"></i>Telemetri</h2>
                    <p class="text-gray-600">Mengirim data 24 jam</p>
                </header>
                @if ($telemetri['sumber']->isEmpty())
                    <p class="px-5 py-8 text-center text-gray-600">Tidak ada pos telemetri.</p>
                @else
                    <ul class="space-y-3 px-4 py-3.5">
                        @foreach ($telemetri['sumber'] as $nama => $t)
                            <li>
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="truncate">{{ $nama }}</span>
                                    <span class="shrink-0 tabular-nums"><span class="font-medium">{{ $t['online'] }}</span><span class="text-gray-600">/{{ $t['total'] }}</span></span>
                                </div>
                                <div class="mt-1 flex h-1.5 overflow-hidden rounded-full {{ $t['online'] < $t['total'] ? 'bg-red-200' : 'bg-gray-100' }}" aria-hidden="true">
                                    <div class="bg-green-600" style="width: {{ round($t['online'] / $t['total'] * 100) }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    @if ($telemetri['mati']->isNotEmpty())
                        <div class="border-t border-gray-100 px-4 py-3">
                            <h3 class="font-medium text-red-700">Tidak mengirim data</h3>
                            <ul class="mt-1.5 space-y-1.5">
                                @foreach ($telemetri['mati'] as $m)
                                    <li class="flex items-baseline justify-between gap-3">
                                        <span class="min-w-0 truncate" title="{{ $m['nama'] }}">{{ $m['nama'] }} <span class="text-gray-600">· {{ $m['sumber'] }}</span></span>
                                        <span class="shrink-0 text-gray-600">{{ $m['waktu'] ? $m['waktu']->diffForHumans(short: true) : 'belum pernah' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @can('superadmin')
                        <a href="{{ route('admin.perangkat-telemetri') }}" class="flex min-h-11 items-center justify-between border-t border-gray-100 px-4 text-primary-700 hover:bg-gray-50 lg:min-h-9">
                            Kelola perangkat telemetri<i class="bi bi-chevron-right text-xs" aria-hidden="true"></i>
                        </a>
                    @endcan
                @endif
            </section>

            {{-- PENGISIAN DATA MANUAL KEMARIN --}}
            <section id="manual" aria-labelledby="judul-manual" class="scroll-mt-4 {{ $kartu }}">
                <header class="{{ $kepala }}">
                    <h2 id="judul-manual" class="flex items-center gap-2 font-medium"><i class="bi bi-clipboard-check text-gray-500" aria-hidden="true"></i>Data manual</h2>
                    <p class="text-gray-600">{{ $kemarin->translatedFormat('j F Y') }}</p>
                </header>
                @forelse ($manual as $m)
                    <div class="border-b border-gray-100 px-4 py-3 last:border-b-0">
                        <div class="flex items-baseline justify-between gap-3">
                            @if ($m['rute'])
                                <a href="{{ route($m['rute']) }}" class="font-medium text-gray-950 underline-offset-2 hover:text-primary-700 hover:underline">{{ $m['label'] }}</a>
                            @else
                                <span class="font-medium">{{ $m['label'] }}</span>
                            @endif
                            <span class="tabular-nums"><span class="font-medium">{{ $m['isi'] }}</span><span class="text-gray-600"> dari {{ $m['total'] }} pos</span></span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                            <div class="h-full bg-primary-600" style="width: {{ round($m['isi'] / $m['total'] * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-gray-600">Tidak ada pos pengamatan manual.</p>
                @endforelse
            </section>

        </div>
    </div>

    @if ($mukaAir->count() > $mukaAirTampil)
        @push('skrip')
            <script>
                // Muka air: 10 pos dengan perubahan terbesar tampil, sisanya dibuka dengan tombol.
                document.getElementById('tampil-muka-air').addEventListener('click', (e) => {
                    const buka = e.currentTarget.getAttribute('aria-expanded') !== 'true';
                    document.querySelectorAll('#muka-air [data-lainnya]').forEach((tr) => (tr.hidden = !buka));
                    e.currentTarget.setAttribute('aria-expanded', String(buka));
                    e.currentTarget.firstChild.textContent = buka ? 'Sembunyikan pos lainnya' : 'Tampilkan {{ $mukaAir->count() - $mukaAirTampil }} pos lainnya';
                });
            </script>
        @endpush
    @endif

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

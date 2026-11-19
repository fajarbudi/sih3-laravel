{{--
    Daftar perangkat telemetri (aplikasi lama: vpostele.php, hanya Beacon): sumber, kode perangkat, sensor, pos,
    faktor skala, aktif, data terakhir diterima. Data terakhir lebih dari 1 hari ditandai, agar perangkat mati terlihat.
--}}
@php
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2.5 py-2';
    $angka = fn ($n) => $n === null ? '' : str_replace('.', ',', rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.'));
@endphp

<x-layouts.admin judul="Pos Telemetri" induk="Pengaturan" :penuh="true">
    <x-slot:aksi>
        <a href="{{ route('admin.perangkat-telemetri.create') }}"
            class="flex min-h-11 items-center gap-1.5 rounded-md bg-primary-500 px-2.5 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 lg:min-h-7">
            <i class="bi bi-plus-lg text-xs" aria-hidden="true"></i> Tambah perangkat
        </a>
    </x-slot:aksi>

    <x-slot:filter>
        <form method="GET" class="flex flex-1 flex-wrap items-center gap-2">
            <i class="bi bi-funnel text-gray-600" aria-hidden="true"></i>
            <label for="sumber" class="sr-only">Sumber</label>
            <select id="sumber" name="sumber" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                <option value="">Semua sumber</option>
                @foreach (App\Enums\SumberTelemetri::cases() as $s)
                    <option value="{{ $s->value }}" @selected(($filter['sumber'] ?? null) === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <label for="aktif" class="sr-only">Status</label>
            <select id="aktif" name="aktif" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                <option value="">Semua status</option>
                <option value="1" @selected(($filter['aktif'] ?? null) === '1')>Aktif</option>
                <option value="0" @selected(($filter['aktif'] ?? null) === '0')>Nonaktif</option>
            </select>
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($filter)
                <a href="{{ route('admin.perangkat-telemetri') }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ number_format($data->total(), 0, ',', '.') }} perangkat</p>
            <label for="cari" class="sr-only">Cari kode atau pos</label>
            <div class="relative ml-auto max-sm:w-full">
                <i class="bi bi-search pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-600" aria-hidden="true"></i>
                <input id="cari" name="cari" type="search" value="{{ $filter['cari'] ?? '' }}" placeholder="Cari kode atau pos" maxlength="100" onchange="this.form.requestSubmit()" class="{{ $pil }} w-full pl-7 sm:w-48">
            </div>
        </form>
    </x-slot:filter>

    @if ($data->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-broadcast text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">{{ $filter ? 'Tidak ada perangkat untuk filter ini' : 'Belum ada perangkat telemetri' }}</p>
            <p class="mt-1 text-gray-600">{{ $filter ? 'Coba ubah atau hapus filter.' : 'Pilih Tambah perangkat untuk memetakan logger ke pos.' }}</p>
        </div>
    @else
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">Perangkat telemetri dan pos tujuannya</caption>
                <thead class="bg-gray-50 align-bottom text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" class="{{ $sel }} font-medium">Sumber</th>
                        <th scope="col" class="{{ $sel }} font-medium">Kode Perangkat</th>
                        <th scope="col" class="{{ $sel }} font-medium">Sensor</th>
                        <th scope="col" class="{{ $sel }} font-medium">POS</th>
                        <th scope="col" class="{{ $sel }} text-right font-medium">Faktor Skala</th>
                        <th scope="col" class="{{ $sel }} font-medium">Status</th>
                        <th scope="col" class="{{ $sel }} font-medium">Data Terakhir</th>
                        <th scope="col" class="{{ $sel }} w-px"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $p)
                        @php($basi = $p->terakhir_diterima_at && $p->terakhir_diterima_at->lt(now()->subDay()))
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $data->firstItem() + $loop->index }}.</td>
                            <td class="{{ $sel }} whitespace-nowrap">{{ $p->sumber->label() }}</td>
                            <td class="{{ $sel }} min-w-32 font-mono text-xs">{{ $p->kode_perangkat }}</td>
                            <td class="{{ $sel }}">{{ $p->sensor }}</td>
                            <td class="{{ $sel }} min-w-40">
                                {{ $p->pos?->nama }}
                                <span class="block text-xs text-gray-600">{{ $p->pos?->jenis?->label() }}{{ $p->pos?->trashed() ? ' · pos dihapus' : '' }}</span>
                            </td>
                            <td class="{{ $sel }} text-right tabular-nums">{{ $angka($p->faktor_skala) }}</td>
                            <td class="{{ $sel }} whitespace-nowrap">{{ $p->aktif ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="{{ $sel }} whitespace-nowrap tabular-nums">
                                @if ($p->terakhir_diterima_at)
                                    <span @class(['font-medium text-red-700' => $basi])>{{ $p->terakhir_diterima_at->translatedFormat('j M Y, H.i') }}</span>
                                    <span class="block text-xs text-gray-600">{{ $basi ? 'Lebih dari 1 hari · ' : '' }}nilai {{ $angka($p->nilai_terakhir) }}</span>
                                @else
                                    <span class="text-gray-600">Belum ada</span>
                                @endif
                            </td>
                            <td class="{{ $sel }} px-1.5 py-1">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('admin.perangkat-telemetri.edit', $p) }}" title="Ubah" aria-label="Ubah perangkat {{ $p->kode_perangkat }}"
                                        class="flex size-11 items-center justify-center rounded-md text-primary-600 hover:bg-primary-50 active:bg-primary-100 lg:size-8">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.perangkat-telemetri.destroy', $p) }}" data-konfirmasi="Perangkat {{ $p->sumber->label() }} {{ $p->kode_perangkat }} untuk pos {{ $p->pos?->nama }} akan dihapus. Data telemetri yang sudah masuk tetap tersimpan.">
                                        @csrf @method('DELETE')
                                        <button title="Hapus" aria-label="Hapus perangkat {{ $p->kode_perangkat }}" class="flex size-11 items-center justify-center rounded-md text-red-700 hover:bg-red-50 active:bg-red-100 lg:size-8">
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.pagination :data="$data" />
    @endif
</x-layouts.admin>

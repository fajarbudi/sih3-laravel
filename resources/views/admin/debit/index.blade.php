@php
    // Angka ditampilkan apa adanya seperti aplikasi lama (tanpa membulatkan), koma sebagai pemisah desimal.
    $angka = fn ($n) => $n === null ? '' : str_replace('.', ',', rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.'));
    $banyakPos = $daftarPos->count() > 1;
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2.5 py-2';
@endphp

<x-layouts.admin judul="Debit" induk="Hidrologi" :penuh="true">
    <x-slot:aksi>
        <a href="{{ route('admin.debit.create') }}"
            class="flex min-h-11 items-center gap-1.5 rounded-md bg-primary-500 px-2.5 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 lg:min-h-7">
            <i class="bi bi-plus-lg text-xs" aria-hidden="true"></i> Tambah data
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
            @if ($banyakPos)
                <label for="pos" class="sr-only">Pos</label>
                <select id="pos" name="pos" onchange="this.form.requestSubmit()" class="{{ $pil }} max-w-48">
                    <option value="">Semua pos</option>
                    @foreach ($daftarPos as $id => $nama)
                        <option value="{{ $id }}" @selected(($filter['pos'] ?? null) == $id)>{{ $nama }}</option>
                    @endforeach
                </select>
            @endif
            <label for="jam" class="sr-only">Jam</label>
            <select id="jam" name="jam" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                <option value="">Semua jam</option>
                @foreach (App\Http\Controllers\Admin\DebitController::JAM as $jam)
                    <option value="{{ $jam }}" @selected(($filter['jam'] ?? null) == $jam)>Jam {{ $jam }}</option>
                @endforeach
            </select>
            <label for="bulan" class="sr-only">Bulan</label>
            <input id="bulan" name="bulan" type="month" value="{{ $filter['bulan'] ?? '' }}" onchange="this.form.requestSubmit()" placeholder="YYYY-MM" class="{{ $pil }} w-40">
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($filter)
                <a href="{{ route('admin.debit') }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ number_format($data->total(), 0, ',', '.') }} data</p>
            {{-- Pencarian di paling kanan; dikirim saat Enter atau saat kolom ditinggalkan --}}
            @if ($banyakPos)
                <label for="cari" class="sr-only">Cari nama pos</label>
                <div class="relative ml-auto max-sm:w-full">
                    <i class="bi bi-search pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-600" aria-hidden="true"></i>
                    <input id="cari" name="cari" type="search" value="{{ $filter['cari'] ?? '' }}" placeholder="Cari nama pos" maxlength="100" onchange="this.form.requestSubmit()" class="{{ $pil }} w-full pl-7 sm:w-48">
                </div>
            @endif
        </form>
    </x-slot:filter>

    @if ($data->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-droplet text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">{{ $filter ? 'Tidak ada data untuk filter ini' : 'Belum ada data debit' }}</p>
            <p class="mt-1 text-gray-600">{{ $filter ? 'Coba ubah atau hapus filter.' : 'Pilih Tambah data untuk mencatat data pertama.' }}</p>
        </div>
    @else
        {{--
            Tabel mengikuti aplikasi lama (vdebit.php): bergaris di semua sel, kolom No., POS, Jam, Waktu,
            debit intake kanan, intake kiri, limpas, debit, keterangan, lalu aksi ubah dan hapus. POS disembunyikan untuk petugas (satu pos).
            relative: agar caption sr-only (absolute) tidak lolos dari wadah gulir dan melebarkan halaman di ponsel.
        --}}
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">Data debit, terbaru di atas</caption>
                <thead class="bg-gray-50 align-bottom text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        @if ($banyakPos)
                            <th scope="col" class="{{ $sel }} font-medium">POS</th>
                        @endif
                        <th scope="col" class="{{ $sel }} font-medium">Jam</th>
                        <th scope="col" class="{{ $sel }} font-medium">Waktu</th>
                        <th scope="col" class="{{ $sel }} min-w-24 text-right font-medium">Debit Intake Kanan (m3/s)</th>
                        <th scope="col" class="{{ $sel }} min-w-24 text-right font-medium">Debit Intake Kiri (m3/s)</th>
                        <th scope="col" class="{{ $sel }} min-w-24 text-right font-medium">Debit Limpas (m3/s)</th>
                        <th scope="col" class="{{ $sel }} min-w-24 text-right font-medium">Debit (m3/s)</th>
                        <th scope="col" class="{{ $sel }} font-medium">Keterangan</th>
                        <th scope="col" class="{{ $sel }} w-px"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $b)
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $data->firstItem() + $loop->index }}.</td>
                            @if ($banyakPos)
                                <td class="{{ $sel }} min-w-32">{{ $b->pos->nama }}</td>
                            @endif
                            <td class="{{ $sel }} tabular-nums">{{ $b->waktu->hour }}</td>
                            <td class="{{ $sel }} whitespace-nowrap tabular-nums">{{ $b->waktu->translatedFormat('j F Y') }} / {{ $b->waktu->format('H:i') }}</td>
                            <td class="{{ $sel }} text-right tabular-nums">{{ $angka($b->intake_kanan) }}</td>
                            <td class="{{ $sel }} text-right tabular-nums">{{ $angka($b->intake_kiri) }}</td>
                            <td class="{{ $sel }} text-right tabular-nums">{{ $angka($b->limpas) }}</td>
                            <td class="{{ $sel }} text-right tabular-nums">{{ $angka($b->debit) }}</td>
                            <td class="{{ $sel }} min-w-24">{{ $b->keterangan }}</td>
                            {{-- Aksi: tombol ikon tanpa bingkai, warna ikon sebagai pembeda (biru = ubah, merah = hapus), selalu terlihat.
                                 Ikon primary-600 6,90:1 dan red-700 6,42:1 di atas putih. --}}
                            <td class="{{ $sel }} px-1.5 py-1">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('admin.debit.edit', $b) }}" title="Ubah" aria-label="Ubah data {{ $b->pos->nama }} {{ $b->waktu->format('d/m/Y H.i') }}"
                                        class="flex size-11 items-center justify-center rounded-md text-primary-600 hover:bg-primary-50 active:bg-primary-100 lg:size-8">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.debit.destroy', $b) }}" data-konfirmasi="Data debit {{ $b->pos->nama }}, {{ $b->waktu->translatedFormat('j F Y') }} pukul {{ $b->waktu->format('H.i') }}, akan dihapus.">
                                        @csrf @method('DELETE')
                                        <button title="Hapus" aria-label="Hapus data {{ $b->pos->nama }} {{ $b->waktu->format('d/m/Y H.i') }}"
                                            class="flex size-11 items-center justify-center rounded-md text-red-700 hover:bg-red-50 active:bg-red-100 lg:size-8">
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

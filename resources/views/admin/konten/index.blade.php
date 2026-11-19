{{--
    Daftar konten generik (Publikasi dan Catatan Kejadian). Kolom dari KontenController::JENIS[jenis]['tabel'],
    label sama dengan aplikasi lama; lampiran diringkas menjadi ikon per file. Aksi ubah/hapus gaya modul lain.
--}}
@php
    $sel = 'border border-gray-200 px-2.5 py-2';
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $kolomJudul = array_key_first($konten['isian']);
    $judulBaris = fn ($b) => $kolomJudul === 'tanggal' ? trim(($b->desa ?? '').' '.$b->tanggal?->translatedFormat('j F Y')) : $b->$kolomJudul;
    $ikonLampiran = ['gambar' => 'bi-image', 'pdf' => 'bi-file-earmark-pdf', 'dokumen' => 'bi-file-earmark-text'];
    $nilai = function ($b, $kolom) {
        $v = match ($kolom) {
            'walidata' => $b->pos?->walidata?->nama ?? $b->walidata?->nama,
            'pos' => $b->pos?->nama,
            'kategori' => $b->kategori?->nama,
            default => $b->$kolom,
        };
        return match (true) {
            is_bool($v) => $v ? 'Ya' : 'Tidak',
            $v instanceof \BackedEnum => method_exists($v, 'label') ? $v->label() : $v->value,
            $v instanceof \Carbon\CarbonInterface => $v->translatedFormat('j F Y'),
            is_int($v) => number_format($v, 0, ',', '.'),
            default => $v,
        };
    };
@endphp

<x-layouts.admin :judul="$konten['judul']" :induk="$konten['induk'] ?? (empty($konten['pos']) || $jenis === 'galeri' ? 'Publikasi' : null)" :penuh="true">
    <x-slot:aksi>
        <a href="{{ route('admin.'.$jenis.'.create') }}"
            class="flex min-h-11 items-center gap-1.5 rounded-md bg-primary-500 px-2.5 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 lg:min-h-7">
            <i class="bi bi-plus-lg text-xs" aria-hidden="true"></i> Tambah data
        </a>
    </x-slot:aksi>

    <x-slot:filter>
        <form method="GET" class="flex flex-1 flex-wrap items-center gap-2">
            <p class="text-gray-600 tabular-nums">{{ number_format($data->total(), 0, ',', '.') }} data</p>
            @if ($cari)
                <a href="{{ route('admin.'.$jenis) }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus pencarian
                </a>
            @endif
            <label for="cari" class="sr-only">Cari {{ strtolower($konten['isian'][$kolomJudul][0]) }}</label>
            <div class="relative ml-auto max-sm:w-full">
                <i class="bi bi-search pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-600" aria-hidden="true"></i>
                <input id="cari" name="cari" type="search" value="{{ $cari }}" placeholder="Cari {{ $kolomJudul === 'tanggal' ? 'desa' : strtolower($konten['isian'][$kolomJudul][0]) }}" maxlength="100"
                    onchange="this.form.requestSubmit()" class="{{ $pil }} w-full pl-7 sm:w-56">
            </div>
        </form>
    </x-slot:filter>

    @if ($data->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-newspaper text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">{{ $cari ? 'Tidak ada data untuk pencarian ini' : 'Belum ada data '.strtolower($konten['judul']) }}</p>
            <p class="mt-1 text-gray-600">{{ $cari ? 'Coba kata lain atau hapus pencarian.' : 'Pilih Tambah data untuk menambahkan.' }}</p>
        </div>
    @else
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">{{ $konten['judul'] }}, terbaru di atas</caption>
                <thead class="bg-gray-50 align-bottom text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        @foreach ($konten['tabel'] as $kolom => $label)
                            <th scope="col" class="{{ $sel }} font-medium">{{ $label }}</th>
                        @endforeach
                        <th scope="col" class="{{ $sel }} w-px"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $b)
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $data->firstItem() + $loop->index }}.</td>
                            @foreach ($konten['tabel'] as $kolom => $label)
                                @if ($kolom === 'lampiran')
                                    <td class="{{ $sel }} px-1.5 py-1">
                                        <div class="flex flex-wrap gap-0.5">
                                            @foreach ($b->lampiran->sortBy('urutan') as $l)
                                                <a href="{{ route('admin.konten.lampiran', $l) }}" target="_blank" title="{{ ucfirst($l->jenis) }} {{ $l->urutan }}" aria-label="Buka {{ $l->jenis }} {{ $l->urutan }} {{ $judulBaris($b) }}"
                                                    class="flex size-11 items-center justify-center rounded-md text-primary-600 hover:bg-primary-50 lg:size-8">
                                                    <i class="bi {{ $ikonLampiran[$l->jenis] ?? 'bi-paperclip' }}" aria-hidden="true"></i>
                                                </a>
                                            @endforeach
                                        </div>
                                    </td>
                                @elseif (in_array($kolom, ['url', 'url_video', 'url_sumber'], true))
                                    <td class="{{ $sel }} max-w-56 truncate">
                                        @if ($b->$kolom) <a href="{{ $b->$kolom }}" target="_blank" rel="noopener" class="text-primary-600 underline-offset-2 hover:underline">{{ Str::limit($b->$kolom, 40) }}</a> @endif
                                    </td>
                                @else
                                    @php($v = $nilai($b, $kolom))
                                    <td class="{{ $sel }} {{ is_numeric($v) ? 'text-right tabular-nums' : '' }} {{ $kolom === $kolomJudul ? 'min-w-48' : '' }} {{ $v instanceof \Carbon\CarbonInterface || in_array($kolom, ['tanggal'], true) ? 'whitespace-nowrap' : '' }}">{{ $v }}</td>
                                @endif
                            @endforeach
                            <td class="{{ $sel }} px-1.5 py-1">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('admin.'.$jenis.'.edit', $b->id) }}" title="Ubah" aria-label="Ubah {{ $judulBaris($b) }}"
                                        class="flex size-11 items-center justify-center rounded-md text-primary-600 hover:bg-primary-50 active:bg-primary-100 lg:size-8">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.'.$jenis.'.destroy', $b->id) }}" data-konfirmasi="{{ $konten['judul'] }} &quot;{{ Str::limit($judulBaris($b), 80) }}&quot; beserta lampirannya akan dihapus.">
                                        @csrf @method('DELETE')
                                        <button title="Hapus" aria-label="Hapus {{ $judulBaris($b) }}" class="flex size-11 items-center justify-center rounded-md text-red-700 hover:bg-red-50 active:bg-red-100 lg:size-8">
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

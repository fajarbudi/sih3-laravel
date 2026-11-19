{{--
    Grid file arsip pos x tahun (aplikasi lama: vfpos.php): No., Walidata / POS (+ tipe - jenis), lalu satu kolom per tahun
    (tahun ini s.d. 10 tahun lalu). Ikon PDF = arsip ada, tanda pisah = belum ada; sel membuka halaman kelola.
--}}
@php
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2.5 py-2';
@endphp

<x-layouts.admin judul="File Arsip" induk="Ketersediaan Data" :penuh="true">
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
            <label for="tipe" class="sr-only">Tipe</label>
            <select id="tipe" name="tipe" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                <option value="">Semua tipe</option>
                @foreach (App\Enums\TipePos::cases() as $t)
                    <option value="{{ $t->value }}" @selected(($filter['tipe'] ?? null) === $t->value)>{{ $t->label() }}</option>
                @endforeach
            </select>
            <label for="jenis" class="sr-only">Jenis</label>
            <select id="jenis" name="jenis" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                <option value="">Semua jenis</option>
                @foreach (App\Enums\JenisPos::cases() as $j)
                    <option value="{{ $j->value }}" @selected(($filter['jenis'] ?? null) === $j->value)>{{ $j->label() }}</option>
                @endforeach
            </select>
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($filter)
                <a href="{{ route('admin.arsip') }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ number_format($pos->total(), 0, ',', '.') }} pos</p>
            <label for="cari" class="sr-only">Cari nama pos</label>
            <div class="relative ml-auto max-sm:w-full">
                <i class="bi bi-search pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-600" aria-hidden="true"></i>
                <input id="cari" name="cari" type="search" value="{{ $filter['cari'] ?? '' }}" placeholder="Cari nama pos" maxlength="100" onchange="this.form.requestSubmit()" class="{{ $pil }} w-full pl-7 sm:w-48">
            </div>
        </form>
    </x-slot:filter>

    @if ($pos->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-archive text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">Tidak ada pos</p>
            <p class="mt-1 text-gray-600">{{ $filter ? 'Coba ubah atau hapus filter.' : 'Belum ada pos yang bisa Anda kelola.' }}</p>
        </div>
    @else
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">File arsip per pos dan tahun</caption>
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" class="{{ $sel }} font-medium">Walidata / POS</th>
                        @foreach ($daftarTahun as $t)
                            <th scope="col" class="{{ $sel }} text-center font-medium tabular-nums">{{ $t }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pos as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $pos->firstItem() + $loop->index }}.</td>
                            <td class="{{ $sel }} min-w-48">
                                <span class="block text-xs italic text-gray-600">{{ $p->walidata?->nama }}</span>
                                <span class="font-medium">{{ $p->nama }}</span>
                                <span class="block text-xs text-gray-600">{{ $p->tipe?->label() }} - {{ $p->jenis?->label() }}</span>
                            </td>
                            @foreach ($daftarTahun as $t)
                                @php($ada = $arsip->get($p->id)?->get($t))
                                <td class="{{ $sel }} p-0 text-center">
                                    <a href="{{ route('admin.arsip.show', [$p, $t]) }}" title="{{ $ada ? 'Arsip '.$t.' ada' : 'Belum ada arsip '.$t }}"
                                        aria-label="{{ $p->nama }} {{ $t }}: {{ $ada ? 'arsip ada, kelola' : 'belum ada arsip, unggah atau generate' }}"
                                        class="flex min-h-11 items-center justify-center hover:bg-primary-50 {{ $ada ? 'text-primary-600' : 'text-gray-600' }}">
                                        @if ($ada)
                                            <i class="bi bi-file-earmark-pdf text-base" aria-hidden="true"></i>
                                        @else
                                            <span aria-hidden="true">&mdash;</span>
                                        @endif
                                    </a>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.pagination :data="$pos" />
    @endif
</x-layouts.admin>

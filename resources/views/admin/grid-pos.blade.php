{{--
    Grid isian per pos (aplikasi lama: vbatas.php dan vrumus_debit.php): No., POS, enam kolom isian angka, satu tombol simpan.
    Dipakai Ambang Batas (Batas 1-6) dan Rumus Debit (R_1-R_6). Variabel: $judul, $kolom (label per indeks), $nilai[pos][indeks],
    $rute (nama route; GET = halaman, PUT = simpan), $keterangan, $pos, $jenis, $cari.
--}}
@php
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2.5 py-2';
@endphp

<x-layouts.admin :judul="$judul" induk="Pengaturan" :penuh="true">
    <x-slot:aksi>
        @if ($pos->isNotEmpty())
            <button form="form-batas" class="flex min-h-11 items-center gap-1.5 rounded-md bg-primary-500 px-2.5 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 lg:min-h-7">
                <i class="bi bi-check2" aria-hidden="true"></i> Simpan {{ strtolower($judul) }}
            </button>
        @endif
    </x-slot:aksi>

    <x-slot:filter>
        <form method="GET" class="flex flex-1 flex-wrap items-center gap-2">
            <i class="bi bi-funnel text-gray-600" aria-hidden="true"></i>
            <label for="jenis" class="sr-only">Jenis pos</label>
            <select id="jenis" name="jenis" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                @foreach (App\Enums\JenisPos::cases() as $j)
                    <option value="{{ $j->value }}" @selected($jenis === $j)>{{ $j->label() }}</option>
                @endforeach
            </select>
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            <p class="text-gray-600 tabular-nums">{{ $pos->count() }} pos</p>
            <label for="cari" class="sr-only">Cari nama pos</label>
            <div class="relative ml-auto max-sm:w-full">
                <i class="bi bi-search pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-600" aria-hidden="true"></i>
                <input id="cari" name="cari" type="search" value="{{ $cari }}" placeholder="Cari nama pos" maxlength="100" onchange="this.form.requestSubmit()" class="{{ $pil }} w-full pl-7 sm:w-48">
            </div>
        </form>
    </x-slot:filter>

    @if ($pos->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-exclamation-triangle text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">Tidak ada pos {{ $jenis->label() }}</p>
            <p class="mt-1 text-gray-600">Pilih jenis lain atau ubah pencarian.</p>
        </div>
    @else
        <form id="form-batas" method="POST" action="{{ route($rute.'.update') }}" class="relative overflow-x-auto px-4 py-4 lg:px-6">
            @csrf @method('PUT')
            <p class="mb-3 text-gray-600">{{ $keterangan }}</p>
            @if ($errors->any())
                <div role="alert" class="mb-3 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                    <i class="bi bi-exclamation-circle-fill mt-px" aria-hidden="true"></i>
                    <p>{{ $judul }} belum tersimpan. Isian yang ditandai harus berupa angka.</p>
                </div>
            @endif
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">{{ $judul }} pos {{ $jenis->label() }}</caption>
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" class="{{ $sel }} font-medium">POS</th>
                        @foreach ($kolom as $level => $labelKolom)
                            <th scope="col" class="{{ $sel }} min-w-24 font-medium">{{ $labelKolom }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pos as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $loop->iteration }}.</td>
                            <th scope="row" class="{{ $sel }} min-w-40 font-normal">{{ $p->nama }}</th>
                            @foreach ($kolom as $level => $labelKolom)
                                @php($kunci = "batas.{$p->id}.{$level}")
                                <td class="{{ $sel }} p-1">
                                    <label for="batas-{{ $p->id }}-{{ $level }}" class="sr-only">{{ $p->nama }} {{ $labelKolom }}</label>
                                    <input id="batas-{{ $p->id }}-{{ $level }}" name="batas[{{ $p->id }}][{{ $level }}]" type="number" step="any" inputmode="decimal"
                                        value="{{ old($kunci, $nilai[$p->id][$level] ?? null) }}" @error($kunci) aria-invalid="true" @enderror
                                        class="block min-h-11 w-full rounded border bg-white px-2 text-right text-sm tabular-nums focus:border-primary-500 focus:outline-2 focus:-outline-offset-1 focus:outline-primary-500 lg:min-h-8 {{ $errors->has($kunci) ? 'border-red-700' : 'border-gray-200 hover:border-gray-450' }}">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </form>
    @endif
</x-layouts.admin>

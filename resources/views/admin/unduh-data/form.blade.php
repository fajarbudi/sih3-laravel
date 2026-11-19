{{--
    Form pengajuan data (aplikasi lama: modal "Pengajuan Data" di vunduh.php): data pemohon, lalu pos dipilih per DAS
    beserta rentang ketersediaannya. DAS ditampilkan sebagai <details> (dulu dropdown "Pilih DAS" + jQuery).
    Tata letak kartu sama dengan form modul lain (lihat admin/duga-air/form).
--}}
@php
    $kontrol = fn ($kolom) => 'block min-h-11 w-full rounded-md border bg-white px-3 text-sm placeholder:text-gray-500 focus:border-primary-500 focus:outline-2 focus:-outline-offset-1 focus:outline-primary-500 lg:min-h-10 '
        .($errors->has($kolom) ? 'border-red-700' : 'border-gray-450 hover:border-gray-600');
    $gagal = fn ($kolom) => $errors->has($kolom) ? "aria-invalid=true aria-describedby=$kolom-error" : '';
    $label = 'mb-1.5 block font-medium';
    $bagian = 'border-t border-gray-200 px-5 py-6 sm:px-8';
    $kolomBagian = 'lg:grid lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-10';
    $bantuan = 'mt-0.5 text-gray-600';
    $pesan = 'mt-1.5 text-xs text-red-700';
    $dipilih = collect(old('pos', []))->map(fn ($id) => (int) $id);
    $rentang = fn ($p) => $p->data_tersedia_dari.' – '.($p->data_tersedia_sampai ?: 'sekarang'); // kolom bertipe YEAR
@endphp

<x-layouts.admin judul="Pengajuan baru" induk="Pengajuan Data" :induk-url="route('admin.unduh.pengajuan')" :penuh="true">
    <div class="flex-1 bg-gray-50 px-4 py-6 lg:px-8 lg:py-8">
        <form method="POST" action="{{ route('admin.unduh.store') }}" class="mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white shadow-xs">
            @csrf

            <div class="px-5 py-6 sm:px-8">
                <h2 class="text-xl font-semibold">Pengajuan Data</h2>
                <p class="{{ $bantuan }}">Permintaan dikirim ke walidata pemilik setiap pos. File arsip bisa diunduh setelah disetujui.</p>
                @if ($errors->any())
                    <div role="alert" class="mt-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <i class="bi bi-exclamation-circle-fill mt-px" aria-hidden="true"></i>
                        <p>Pengajuan belum terkirim. Perbaiki {{ $errors->count() === 1 ? 'isian' : $errors->count().' isian' }} yang ditandai.</p>
                    </div>
                @endif
            </div>

            {{-- DATA PEMOHON --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-pemohon">
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-pemohon" class="font-semibold">Data pemohon</h3>
                        <p class="{{ $bantuan }}">Nama wajib diisi.</p>
                    </div>
                    <div class="grid gap-5 pt-5 sm:grid-cols-2 lg:pt-0">
                        @foreach (['nama_pemohon' => ['Nama', auth()->user()->nama], 'instansi' => ['Instansi', auth()->user()->walidata?->nama], 'jabatan' => ['Jabatan', null], 'tujuan' => ['Tujuan', null]] as $kolom => [$teks, $bawaan])
                            <div>
                                <label for="{{ $kolom }}" class="{{ $label }}">{{ $teks }}</label>
                                <input id="{{ $kolom }}" name="{{ $kolom }}" type="text" maxlength="{{ $kolom === 'tujuan' ? 255 : 150 }}" value="{{ old($kolom, $bawaan) }}"
                                    @if ($kolom === 'nama_pemohon') required @endif class="{{ $kontrol($kolom) }}" {!! $gagal($kolom) !!}>
                                @error($kolom) <p id="{{ $kolom }}-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </fieldset>

            {{-- PILIH POS --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-pos" @if ($errors->has('pos')) aria-describedby="pos-error" @endif>
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-pos" class="font-semibold">Pilih pos</h3>
                        <p class="{{ $bantuan }}">Buka DAS, lalu centang pos yang datanya diminta. Rentang tahun = ketersediaan arsip.</p>
                        @error('pos') <p id="pos-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-2 pt-5 lg:pt-0">
                        @forelse ($posPerDas as $das => $daftar)
                            @php($adaDipilih = $daftar->pluck('id')->intersect($dipilih)->isNotEmpty())
                            <details class="group rounded-lg border border-gray-200" @if ($adaDipilih) open @endif>
                                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-4 font-medium hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-primary-500">
                                    <span>{{ $das }}</span>
                                    <span class="flex items-center gap-2 text-gray-600">
                                        <span class="text-xs tabular-nums">{{ $daftar->count() }} pos</span>
                                        <i class="bi bi-chevron-down text-xs transition-transform group-open:rotate-180 motion-reduce:transition-none" aria-hidden="true"></i>
                                    </span>
                                </summary>
                                <div class="grid gap-x-6 border-t border-gray-200 px-4 py-3 sm:grid-cols-2">
                                    @foreach ($daftar as $p)
                                        <label class="flex min-h-11 cursor-pointer items-center gap-2.5 lg:min-h-9">
                                            <input type="checkbox" name="pos[]" value="{{ $p->id }}" @checked($dipilih->contains($p->id)) class="size-4 rounded border-gray-450 accent-primary-500">
                                            <span>{{ $p->nama }} <span class="text-gray-600 tabular-nums">[{{ $rentang($p) }}]</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @empty
                            <p class="rounded-lg bg-gray-100 px-4 py-3.5 text-gray-600">Belum ada pos dengan data arsip yang tersedia.</p>
                        @endforelse
                    </div>
                </div>
            </fieldset>

            <div class="flex flex-wrap items-center justify-end gap-3 rounded-b-xl border-t border-gray-200 px-5 py-4 sm:px-8">
                <a href="{{ route('admin.unduh.pengajuan') }}" class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md border border-gray-450 bg-white px-3.5 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 sm:flex-none lg:min-h-9">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Batal
                </a>
                <button class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md bg-primary-500 px-4 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 sm:flex-none lg:min-h-9">
                    <i class="bi bi-send" aria-hidden="true"></i> Kirim pengajuan
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>

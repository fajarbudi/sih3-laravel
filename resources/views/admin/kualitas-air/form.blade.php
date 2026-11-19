{{--
    Form kualitas air: tata letak sama dengan form duga air (lihat admin/duga-air/form).
    Isian seperti aplikasi lama: pos, tanggal (tanpa jam), lokasi, parameter uji (4 desimal), index pencemaran,
    kondisi fisik lapangan (teks), keterangan. Daftar parameter diambil dari tabel parameter_kualitas_air.
--}}
@php
    $baru = ! $kualitasAir->exists;
    $nilai = fn ($kolom) => old($kolom, $kualitasAir->$kolom);
    $gagal = fn ($kolom) => $errors->has($kolom) ? "aria-invalid=true aria-describedby=$kolom-error" : '';
    // Satu gaya kontrol. focus:outline juga untuk isian tanggal Chrome, yang tidak kena :focus-visible.
    $kontrol = fn ($kolom) => 'block min-h-11 w-full rounded-md border bg-white px-3 text-sm placeholder:text-gray-500 focus:border-primary-500 focus:outline-2 focus:-outline-offset-1 focus:outline-primary-500 lg:min-h-10 '
        .($errors->has($kolom) ? 'border-red-700' : 'border-gray-450 hover:border-gray-600');
    $label = 'mb-1.5 block font-medium';
    $bagian = 'border-t border-gray-200 px-5 py-6 sm:px-8';
    $judulBagian = 'font-semibold';
    $kolomBagian = 'lg:grid lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-10'; // judul kiri, isian kanan
    $bantuan = 'mt-0.5 text-gray-600';
    $pesan = 'mt-1.5 text-xs text-red-700';
    $tanpaPos = ! $petugas && $daftarPos->isEmpty();
@endphp

<x-layouts.admin :judul="$baru ? 'Data baru' : 'Ubah data'" induk="Kualitas Air" :induk-url="route('admin.kualitas-air')" :penuh="true">
    <div class="flex-1 bg-gray-50 px-4 py-6 lg:px-8 lg:py-8">
        <form id="form-kualitas-air" method="POST" action="{{ $baru ? route('admin.kualitas-air.store') : route('admin.kualitas-air.update', $kualitasAir) }}"
            class="mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white shadow-xs">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            {{-- Kepala --}}
            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2 px-5 py-6 sm:px-8">
                <div>
                    <h2 class="text-xl font-semibold">{{ $baru ? 'Tambah Data Kualitas Air' : 'Ubah Data Kualitas Air' }}</h2>
                    <p class="{{ $bantuan }}">
                        @if ($baru)
                            Hasil uji satu sampel air. Kosongkan parameter yang tidak diuji.
                        @else
                            {{ $kualitasAir->waktu->translatedFormat('l, j F Y') }}{{ $kualitasAir->lokasi ? ' · '.$kualitasAir->lokasi : '' }}
                        @endif
                    </p>
                </div>
                @unless ($baru)
                    <p class="text-xs text-gray-600 sm:pt-2">
                        Dicatat {{ $kualitasAir->created_at?->translatedFormat('j M Y, H.i') ?? 'tanpa waktu' }}{{ $kualitasAir->penginput ? ' oleh '.$kualitasAir->penginput->nama : '' }}
                    </p>
                @endunless

                @if ($tanpaPos)
                    {{-- Keadaan kosong: admin walidata tanpa pos kualitas air --}}
                    <div role="status" class="w-full rounded-lg bg-gray-100 px-4 py-3.5">
                        <p class="font-medium">Belum ada pos kualitas air yang bisa Anda kelola</p>
                        <p class="mt-0.5 text-gray-600">Minta superadmin menambahkan pos kualitas air ke walidata Anda, lalu buka halaman ini lagi.</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div role="alert" class="flex w-full gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <i class="bi bi-exclamation-circle-fill mt-px" aria-hidden="true"></i>
                        <p>Data belum tersimpan. Perbaiki {{ $errors->count() === 1 ? 'isian' : $errors->count().' isian' }} yang ditandai.</p>
                    </div>
                @endif
            </div>

            {{-- LOKASI DAN WAKTU --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-1" @disabled($tanpaPos)>
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-1" class="{{ $judulBagian }}">Lokasi dan waktu</h3>
                        <p class="{{ $bantuan }}">Pos dan tanggal wajib diisi.</p>
                    </div>
                <div class="grid gap-5 pt-5 sm:grid-cols-2 lg:pt-0">
                    <div class="sm:col-span-2">
                        @if ($petugas || $tanpaPos)
                            <p class="{{ $label }}">Pos</p>
                            <p class="flex min-h-11 items-center rounded-md bg-gray-100 px-3 font-medium lg:min-h-10">{{ $petugas ? auth()->user()->pos->nama : 'Tidak ada pos kualitas air' }}</p>
                        @else
                            <label for="pos_id" class="{{ $label }}">Pos</label>
                            <select id="pos_id" name="pos_id" required class="{{ $kontrol('pos_id') }}" {!! $gagal('pos_id') !!}>
                                <option value="">Pilih pos</option>
                                @foreach ($daftarPos as $id => $nama)
                                    <option value="{{ $id }}" @selected($nilai('pos_id') == $id)>{{ $nama }}</option>
                                @endforeach
                            </select>
                            @error('pos_id') <p id="pos_id-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <div>
                            <label for="tanggal" class="{{ $label }}">Tanggal</label>
                            <input id="tanggal" name="tanggal" type="date" required max="{{ today()->toDateString() }}" value="{{ old('tanggal', $kualitasAir->waktu?->toDateString()) }}"
                                class="{{ $kontrol('tanggal') }} tabular-nums" {!! $gagal('tanggal') !!}>
                            @error('tanggal') <p id="tanggal-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="lokasi" class="{{ $label }}">Lokasi pengamatan</label>
                        <input id="lokasi" name="lokasi" type="text" maxlength="255" value="{{ $nilai('lokasi') }}" placeholder="Desa atau kode titik"
                            class="{{ $kontrol('lokasi') }}" {!! $gagal('lokasi') !!}>
                        @error('lokasi') <p id="lokasi-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                    </div>
                </div>
                </div>
            </fieldset>

            {{-- HASIL UJI --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-uji" @disabled($tanpaPos)>
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-uji" class="{{ $judulBagian }}">Hasil uji</h3>
                        <p class="{{ $bantuan }}">Kosongkan parameter yang tidak diuji. Desimal sampai 4 angka.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-5 gap-y-4 pt-5 sm:grid-cols-4 lg:pt-0">
                        @foreach ($parameter as $p)
                            @php($kunci = 'nilai.'.$p->kode)
                            {{-- flex-col + label flex-1: kotak isian sebaris tetap sejajar walau labelnya dua baris --}}
                            <div class="flex flex-col">
                                <label for="nilai-{{ $p->kode }}" class="{{ $label }} flex-1">{{ $p->nama }} @if ($p->satuan)<span class="font-normal text-gray-600">({{ $p->satuan }})</span>@endif</label>
                                <input id="nilai-{{ $p->kode }}" name="nilai[{{ $p->kode }}]" type="number" step="0.0001" inputmode="decimal"
                                    value="{{ old($kunci, $nilaiLama[$p->kode] ?? null) }}" class="{{ $kontrol($kunci) }} tabular-nums"
                                    @error($kunci) aria-invalid="true" aria-describedby="nilai-{{ $p->kode }}-error" @enderror>
                                @error($kunci) <p id="nilai-{{ $p->kode }}-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </fieldset>

            {{-- INDEX PENCEMARAN --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-ip" @disabled($tanpaPos)>
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-ip" class="{{ $judulBagian }}">Index pencemaran</h3>
                        <p class="{{ $bantuan }}">Ditampilkan di daftar data.</p>
                    </div>
                    <div class="grid gap-5 pt-5 sm:grid-cols-4 lg:pt-0">
                        <div>
                            <label for="indeks_pencemaran" class="{{ $label }}">Index pencemaran</label>
                            <input id="indeks_pencemaran" name="indeks_pencemaran" type="number" step="0.0001" inputmode="decimal" value="{{ $nilai('indeks_pencemaran') }}"
                                class="{{ $kontrol('indeks_pencemaran') }} min-h-12 text-lg font-semibold tabular-nums lg:min-h-12" {!! $gagal('indeks_pencemaran') !!}>
                            @error('indeks_pencemaran') <p id="indeks_pencemaran-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </fieldset>

            {{-- KONDISI LAPANGAN --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-fisik" @disabled($tanpaPos)>
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-fisik" class="{{ $judulBagian }}">Kondisi lapangan</h3>
                        <p class="{{ $bantuan }}">Pengamatan fisik saat sampel diambil. Opsional.</p>
                    </div>
                    <div class="grid gap-x-5 gap-y-4 pt-5 sm:grid-cols-3 lg:pt-0">
                        @foreach (App\Http\Controllers\Admin\KualitasAirController::FISIK as $kolom => $teks)
                            <div>
                                <label for="{{ $kolom }}" class="{{ $label }}">{{ $teks }}</label>
                                <input id="{{ $kolom }}" name="{{ $kolom }}" type="text" maxlength="255" value="{{ $nilai($kolom) }}"
                                    class="{{ $kontrol($kolom) }}" {!! $gagal($kolom) !!}>
                                @error($kolom) <p id="{{ $kolom }}-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </fieldset>

            {{-- CATATAN --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-3" @disabled($tanpaPos)>
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-3" class="{{ $judulBagian }}">Catatan</h3>
                        <p class="{{ $bantuan }}">Opsional. Tulis kondisi lapangan yang memengaruhi pembacaan.</p>
                    </div>
                <div class="grid gap-5 pt-5 lg:pt-0">
                    <div>
                        <label for="keterangan" class="{{ $label }}">Keterangan</label>
                        <textarea id="keterangan" name="keterangan" rows="3" maxlength="255" placeholder="Misalnya sampel diuji di laboratorium mitra"
                            class="{{ $kontrol('keterangan') }} resize-y py-2.5 leading-relaxed" @unless ($errors->has('keterangan')) aria-describedby="keterangan-hitung" @endunless {!! $gagal('keterangan') !!}>{{ $nilai('keterangan') }}</textarea>
                        <p id="keterangan-hitung" class="mt-1.5 text-right text-xs text-gray-600 tabular-nums"><span data-hitung>{{ mb_strlen((string) $nilai('keterangan')) }}</span>/255 karakter</p>
                        @error('keterangan') <p id="keterangan-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                    </div>

                    {{-- Status validasi: hanya admin, saat mengubah. Sakelar = kotak centang dengan role="switch". --}}
                    @unless ($petugas || $baru)
                        <div>
                            <label for="tervalidasi" class="{{ $label }}">Status</label>
                            <label class="flex min-h-11 cursor-pointer items-center gap-2.5 lg:min-h-10">
                                <input id="tervalidasi" type="checkbox" name="tervalidasi" value="1" role="switch" class="peer sr-only" @checked(old('tervalidasi', $kualitasAir->tervalidasi))>
                                <span class="relative h-6 w-11 shrink-0 rounded-full bg-gray-450 transition-colors peer-checked:bg-primary-500 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-500 motion-reduce:transition-none
                                    after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-xs after:transition-transform peer-checked:after:translate-x-5 motion-reduce:after:transition-none" aria-hidden="true"></span>
                                {{-- Teks mengikuti posisi sakelar (sakelar sendiri sudah mengumumkan statusnya ke pembaca layar) --}}
                                <span class="hidden peer-checked:inline" aria-hidden="true">Tervalidasi</span>
                                <span class="text-gray-600 peer-checked:hidden" aria-hidden="true">Belum tervalidasi</span>
                            </label>
                        </div>
                    @endunless
                </div>
                </div>
            </fieldset>

            {{-- AKSI --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-b-xl border-t border-gray-200 px-5 py-4 sm:px-8">
                @unless ($baru)
                    <button type="submit" form="form-hapus" class="-ml-3 flex min-h-11 items-center gap-1.5 rounded-md px-3 text-red-700 hover:bg-red-50 active:bg-red-100 lg:min-h-9">
                        <i class="bi bi-trash3" aria-hidden="true"></i> Hapus data
                    </button>
                @endunless
                <div class="flex w-full flex-wrap items-center justify-end gap-x-3 gap-y-2 sm:ml-auto sm:w-auto">
                    <a href="{{ route('admin.kualitas-air') }}" class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md border border-gray-450 bg-white px-3.5 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 sm:flex-none lg:min-h-9">
                        <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Batal
                    </a>
                    <button data-simpan @disabled($tanpaPos)
                        class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md bg-primary-500 px-4 font-medium text-white shadow-xs sm:flex-none hover:bg-primary-600 active:bg-primary-700 disabled:bg-gray-200 disabled:text-gray-600 disabled:shadow-none lg:min-h-9">
                        <i class="bi bi-check2" aria-hidden="true"></i> <span>{{ $baru ? 'Simpan data' : 'Simpan perubahan' }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    @unless ($baru)
        <form id="form-hapus" method="POST" action="{{ route('admin.kualitas-air.destroy', $kualitasAir) }}"
            data-konfirmasi="Data kualitas air {{ $kualitasAir->pos->nama }}, {{ $kualitasAir->waktu->translatedFormat('j F Y') }}, akan dihapus.">
            @csrf @method('DELETE')
        </form>
    @endunless

    @push('skrip')
        <script>
            // Penghitung karakter keterangan.
            const keterangan = document.getElementById('keterangan');
            keterangan?.addEventListener('input', () => {
                document.querySelector('[data-hitung]').textContent = keterangan.value.length;
            });

            // Keadaan menyimpan: tombol dikunci agar data tidak terkirim dua kali.
            document.getElementById('form-kualitas-air').addEventListener('submit', () => {
                const tombol = document.querySelector('[data-simpan]');
                tombol.disabled = true;
                tombol.querySelector('i').className = 'bi bi-arrow-repeat motion-safe:animate-spin';
                tombol.querySelector('span').textContent = 'Menyimpan…';
            });
        </script>
    @endpush
</x-layouts.admin>

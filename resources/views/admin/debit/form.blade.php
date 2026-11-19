{{--
    Form debit: tata letak sama dengan form duga air (lihat admin/duga-air/form); empat nilai debit dalam m3/s.
--}}
@php
    $baru = ! $debit->exists;
    $nilai = fn ($kolom) => old($kolom, $debit->$kolom);
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
    $ukuran = ['intake_kanan' => 'Intake kanan', 'intake_kiri' => 'Intake kiri', 'limpas' => 'Limpas', 'debit' => 'Debit'];
    $tanpaPos = ! $petugas && $daftarPos->isEmpty();
@endphp

<x-layouts.admin :judul="$baru ? 'Data baru' : 'Ubah data'" induk="Debit" :induk-url="route('admin.debit')" :penuh="true">
    <div class="flex-1 bg-gray-50 px-4 py-6 lg:px-8 lg:py-8">
        <form id="form-debit" method="POST" action="{{ $baru ? route('admin.debit.store') : route('admin.debit.update', $debit) }}"
            class="mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white shadow-xs">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            {{-- Kepala --}}
            <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2 px-5 py-6 sm:px-8">
                <div>
                    <h2 class="text-xl font-semibold">{{ $baru ? 'Tambah Data Debit' : 'Ubah Data Debit' }}</h2>
                    <p class="{{ $bantuan }}">
                        @if ($baru)
                            Pengamatan manual pos AWLR, dicatat pada jam 07.00, 12.00, atau 17.00.
                        @else
                            {{ $debit->waktu->translatedFormat('l, j F Y') }} pukul {{ $debit->waktu->format('H.i') }}
                        @endif
                    </p>
                </div>
                @unless ($baru)
                    <p class="text-xs text-gray-600 sm:pt-2">
                        Dicatat {{ $debit->created_at?->translatedFormat('j M Y, H.i') ?? 'tanpa waktu' }}{{ $debit->penginput ? ' oleh '.$debit->penginput->nama : '' }}
                    </p>
                @endunless

                @if ($tanpaPos)
                    {{-- Keadaan kosong: admin walidata tanpa pos AWLR --}}
                    <div role="status" class="w-full rounded-lg bg-gray-100 px-4 py-3.5">
                        <p class="font-medium">Belum ada pos AWLR yang bisa Anda kelola</p>
                        <p class="mt-0.5 text-gray-600">Minta superadmin menambahkan pos AWLR ke walidata Anda, lalu buka halaman ini lagi.</p>
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
                        <p class="{{ $bantuan }}">Pos dan waktu pengamatan. Semua wajib diisi.</p>
                    </div>
                <div class="grid gap-5 pt-5 sm:grid-cols-2 lg:pt-0">
                    <div class="sm:col-span-2">
                        @if ($petugas || $tanpaPos)
                            <p class="{{ $label }}">Pos</p>
                            <p class="flex min-h-11 items-center rounded-md bg-gray-100 px-3 font-medium lg:min-h-10">{{ $petugas ? auth()->user()->pos->nama : 'Tidak ada pos AWLR' }}</p>
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
                        @if ($petugas && $baru)
                            {{-- Petugas hanya mencatat untuk hari ini, seperti aplikasi lama. --}}
                            <p class="{{ $label }}">Tanggal</p>
                            <p class="flex min-h-11 items-center rounded-md bg-gray-100 px-3 lg:min-h-10">Hari ini, {{ today()->translatedFormat('j M Y') }}</p>
                        @else
                            <label for="tanggal" class="{{ $label }}">Tanggal</label>
                            <input id="tanggal" name="tanggal" type="date" required max="{{ today()->toDateString() }}" value="{{ old('tanggal', $debit->waktu?->toDateString()) }}"
                                class="{{ $kontrol('tanggal') }} tabular-nums" {!! $gagal('tanggal') !!}>
                            @error('tanggal') <p id="tanggal-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <div>
                        <span id="label-jam" class="{{ $label }}">Jam</span>
                        <div role="radiogroup" aria-labelledby="label-jam" aria-required="true" @if ($errors->has('jam')) aria-describedby="jam-error" @endif
                            class="grid grid-cols-3 gap-1 rounded-md border border-gray-450 bg-white p-0.5">
                            @foreach (App\Http\Controllers\Admin\DebitController::JAM as $jam)
                                <label class="flex min-h-11 cursor-pointer items-center justify-center rounded text-gray-700 tabular-nums hover:bg-gray-100 has-checked:bg-primary-50 has-checked:font-semibold has-checked:text-primary-700 has-checked:ring-1 has-checked:ring-primary-500 has-focus-visible:outline-2 has-focus-visible:outline-primary-500 lg:min-h-[2.125rem]">
                                    <input type="radio" name="jam" value="{{ $jam }}" class="sr-only" required @checked(old('jam', $debit->waktu?->hour ?? 7) == $jam)>
                                    {{ sprintf('%02d.00', $jam) }}
                                </label>
                            @endforeach
                        </div>
                        @error('jam') <p id="jam-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                    </div>
                </div>
                </div>
            </fieldset>

            {{-- DEBIT --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-2" @disabled($tanpaPos)>
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-2" class="{{ $judulBagian }}">Debit</h3>
                        <p class="{{ $bantuan }}">Dalam m³/detik. Kosongkan kolom yang tidak diukur.</p>
                    </div>
                <div class="grid grid-cols-2 gap-5 pt-5 sm:grid-cols-4 lg:pt-0">
                    @foreach ($ukuran as $kolom => $teks)
                        <div>
                            <label for="{{ $kolom }}" class="{{ $label }}">{{ $teks }}</label>
                            <div class="relative">
                                <input id="{{ $kolom }}" name="{{ $kolom }}" type="number" step="0.001" inputmode="decimal" value="{{ $nilai($kolom) }}"
                                    class="{{ $kontrol($kolom) }} min-h-12 pr-12 text-lg font-semibold tabular-nums lg:min-h-12" {!! $gagal($kolom) !!}>
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-600" aria-hidden="true">m³/s</span>
                            </div>
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
                        <textarea id="keterangan" name="keterangan" rows="3" maxlength="255" placeholder="Misalnya pintu intake ditutup untuk perbaikan"
                            class="{{ $kontrol('keterangan') }} resize-y py-2.5 leading-relaxed" @unless ($errors->has('keterangan')) aria-describedby="keterangan-hitung" @endunless {!! $gagal('keterangan') !!}>{{ $nilai('keterangan') }}</textarea>
                        <p id="keterangan-hitung" class="mt-1.5 text-right text-xs text-gray-600 tabular-nums"><span data-hitung>{{ mb_strlen((string) $nilai('keterangan')) }}</span>/255 karakter</p>
                        @error('keterangan') <p id="keterangan-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                    </div>

                    {{-- Status validasi: hanya admin, saat mengubah. Sakelar = kotak centang dengan role="switch". --}}
                    @unless ($petugas || $baru)
                        <div>
                            <label for="tervalidasi" class="{{ $label }}">Status</label>
                            <label class="flex min-h-11 cursor-pointer items-center gap-2.5 lg:min-h-10">
                                <input id="tervalidasi" type="checkbox" name="tervalidasi" value="1" role="switch" class="peer sr-only" @checked(old('tervalidasi', $debit->tervalidasi))>
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
                    <a href="{{ route('admin.debit') }}" class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md border border-gray-450 bg-white px-3.5 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 sm:flex-none lg:min-h-9">
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
        <form id="form-hapus" method="POST" action="{{ route('admin.debit.destroy', $debit) }}"
            data-konfirmasi="Data debit {{ $debit->pos->nama }}, {{ $debit->waktu->translatedFormat('j F Y') }} pukul {{ $debit->waktu->format('H.i') }}, akan dihapus.">
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
            document.getElementById('form-debit').addEventListener('submit', () => {
                const tombol = document.querySelector('[data-simpan]');
                tombol.disabled = true;
                tombol.querySelector('i').className = 'bi bi-arrow-repeat motion-safe:animate-spin';
                tombol.querySelector('span').textContent = 'Menyimpan…';
            });
        </script>
    @endpush
</x-layouts.admin>

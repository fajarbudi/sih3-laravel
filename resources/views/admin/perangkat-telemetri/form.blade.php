{{--
    Form perangkat telemetri: sumber, kode perangkat (ID logger/nama stasiun di sistem vendor), sensor (Beacon),
    pos tujuan, faktor skala (nilai mentah x faktor = satuan baku), aktif. Tata letak kartu sama dengan form modul lain.
--}}
@php
    $baru = ! $perangkat->exists;
    $kontrol = fn ($kolom) => 'block min-h-11 w-full rounded-md border bg-white px-3 text-sm placeholder:text-gray-500 focus:border-primary-500 focus:outline-2 focus:-outline-offset-1 focus:outline-primary-500 lg:min-h-10 '
        .($errors->has($kolom) ? 'border-red-700' : 'border-gray-450 hover:border-gray-600');
    $gagal = fn ($kolom) => $errors->has($kolom) ? "aria-invalid=true aria-describedby=$kolom-error" : '';
    $label = 'mb-1.5 block font-medium';
    $bantuan = 'mt-1.5 text-xs text-gray-600';
    $pesan = 'mt-1.5 text-xs text-red-700';
    $nilai = fn ($kolom) => old($kolom, $perangkat->$kolom instanceof BackedEnum ? $perangkat->$kolom->value : $perangkat->$kolom);
@endphp

<x-layouts.admin :judul="$baru ? 'Perangkat baru' : 'Ubah perangkat'" induk="Pos Telemetri" :induk-url="route('admin.perangkat-telemetri')" :penuh="true">
    <div class="flex-1 bg-gray-50 px-4 py-6 lg:px-8 lg:py-8">
        <form method="POST" action="{{ $baru ? route('admin.perangkat-telemetri.store') : route('admin.perangkat-telemetri.update', $perangkat) }}"
            class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white shadow-xs">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            <div class="px-5 py-6 sm:px-8">
                <h2 class="text-xl font-semibold">{{ $baru ? 'Tambah Perangkat Telemetri' : 'Ubah Perangkat Telemetri' }}</h2>
                <p class="mt-0.5 text-gray-600">Data dari perangkat ini akan disimpan ke pos yang dipilih saat penarikan berikutnya.</p>
                @if ($errors->any())
                    <div role="alert" class="mt-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <i class="bi bi-exclamation-circle-fill mt-px" aria-hidden="true"></i>
                        <p>Data belum tersimpan. Perbaiki {{ $errors->count() === 1 ? 'isian' : $errors->count().' isian' }} yang ditandai.</p>
                    </div>
                @endif
            </div>

            <div class="grid gap-5 border-t border-gray-200 px-5 py-6 sm:grid-cols-2 sm:px-8">
                <div>
                    <label for="sumber" class="{{ $label }}">Sumber</label>
                    <select id="sumber" name="sumber" required class="{{ $kontrol('sumber') }}" {!! $gagal('sumber') !!}>
                        <option value="">Pilih sumber</option>
                        @foreach (App\Enums\SumberTelemetri::cases() as $s)
                            <option value="{{ $s->value }}" @selected($nilai('sumber') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                    @error('sumber') <p id="sumber-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="kode_perangkat" class="{{ $label }}">Kode perangkat</label>
                    <input id="kode_perangkat" name="kode_perangkat" type="text" required maxlength="100" value="{{ $nilai('kode_perangkat') }}"
                        aria-describedby="kode-bantuan" class="{{ $kontrol('kode_perangkat') }} font-mono" {!! $gagal('kode_perangkat') !!}>
                    <p id="kode-bantuan" class="{{ $bantuan }}">ID logger, atau nama stasiun untuk PJT.</p>
                    @error('kode_perangkat') <p id="kode_perangkat-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="pos_id" class="{{ $label }}">POS</label>
                    <select id="pos_id" name="pos_id" required class="{{ $kontrol('pos_id') }}" {!! $gagal('pos_id') !!}>
                        <option value="">Pilih pos</option>
                        @foreach ($daftarPos as $p)
                            <option value="{{ $p->id }}" @selected((string) $nilai('pos_id') === (string) $p->id)>{{ $p->nama }} ({{ $p->jenis?->label() }})</option>
                        @endforeach
                    </select>
                    @error('pos_id') <p id="pos_id-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sensor" class="{{ $label }}">Sensor</label>
                    <input id="sensor" name="sensor" type="text" maxlength="50" value="{{ $nilai('sensor') }}" placeholder="mis. sensor1"
                        aria-describedby="sensor-bantuan" class="{{ $kontrol('sensor') }}" {!! $gagal('sensor') !!}>
                    <p id="sensor-bantuan" class="{{ $bantuan }}">Kanal sensor, khusus Beacon.</p>
                    @error('sensor') <p id="sensor-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="faktor_skala" class="{{ $label }}">Faktor skala</label>
                    <input id="faktor_skala" name="faktor_skala" type="number" step="any" required value="{{ $nilai('faktor_skala') }}"
                        aria-describedby="faktor-bantuan" class="{{ $kontrol('faktor_skala') }} tabular-nums" {!! $gagal('faktor_skala') !!}>
                    <p id="faktor-bantuan" class="{{ $bantuan }}">Nilai mentah × faktor = satuan baku. Isi 0,01 bila alat mengirim cm.</p>
                    @error('faktor_skala') <p id="faktor_skala-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <span class="{{ $label }}">Status</span>
                    <label class="flex min-h-11 cursor-pointer items-center gap-2.5 lg:min-h-10">
                        <input type="checkbox" name="aktif" value="1" role="switch" class="peer sr-only" @checked(old('aktif', $perangkat->aktif))>
                        <span class="relative h-6 w-11 shrink-0 rounded-full bg-gray-450 transition-colors peer-checked:bg-primary-500 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-500 motion-reduce:transition-none
                            after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-xs after:transition-transform peer-checked:after:translate-x-5 motion-reduce:after:transition-none" aria-hidden="true"></span>
                        <span class="hidden peer-checked:inline" aria-hidden="true">Aktif, data ditarik</span>
                        <span class="text-gray-600 peer-checked:hidden" aria-hidden="true">Nonaktif</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 rounded-b-xl border-t border-gray-200 px-5 py-4 sm:px-8">
                <a href="{{ route('admin.perangkat-telemetri') }}" class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md border border-gray-450 bg-white px-3.5 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 sm:flex-none lg:min-h-9">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Batal
                </a>
                <button class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md bg-primary-500 px-4 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 sm:flex-none lg:min-h-9">
                    <i class="bi bi-check2" aria-hidden="true"></i> {{ $baru ? 'Simpan perangkat' : 'Simpan perubahan' }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.admin>

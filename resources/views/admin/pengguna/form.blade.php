{{--
    Form pengguna: data akun (nama, nama login, sandi), level beserta walidata/pos sesuai level, kontak, status.
    Isian walidata hanya tampil untuk Admin Walidata, isian pos untuk Petugas Pos (skrip kecil; tanpa JS keduanya tampil).
--}}
@php
    $baru = ! $pengguna->exists;
    $sendiri = $pengguna->is(auth()->user());
    $kontrol = fn ($kolom) => 'block min-h-11 w-full rounded-md border bg-white px-3 text-sm placeholder:text-gray-500 focus:border-primary-500 focus:outline-2 focus:-outline-offset-1 focus:outline-primary-500 lg:min-h-10 '
        .($errors->has($kolom) ? 'border-red-700' : 'border-gray-450 hover:border-gray-600');
    $gagal = fn ($kolom) => $errors->has($kolom) ? "aria-invalid=true aria-describedby=$kolom-error" : '';
    $label = 'mb-1.5 block font-medium';
    $bantuan = 'mt-1.5 text-xs text-gray-600';
    $pesan = 'mt-1.5 text-xs text-red-700';
    $peran = old('peran', $pengguna->peran?->value);
@endphp

<x-layouts.admin :judul="$baru ? 'Pengguna baru' : 'Ubah pengguna'" induk="Pengguna" :induk-url="route('admin.pengguna')" :penuh="true">
    <div class="flex-1 bg-gray-50 px-4 py-6 lg:px-8 lg:py-8">
        <form method="POST" action="{{ $baru ? route('admin.pengguna.store') : route('admin.pengguna.update', $pengguna) }}"
            class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white shadow-xs">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            <div class="px-5 py-6 sm:px-8">
                <h2 class="text-xl font-semibold">{{ $baru ? 'Tambah Pengguna' : 'Ubah Pengguna' }}</h2>
                <p class="mt-0.5 text-gray-600">{{ $baru ? 'Akun baru langsung bisa masuk ke panel admin.' : 'Kosongkan sandi bila tidak ingin mengubahnya.' }}</p>
                @if ($errors->any())
                    <div role="alert" class="mt-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <i class="bi bi-exclamation-circle-fill mt-px" aria-hidden="true"></i>
                        <p>Data belum tersimpan. Perbaiki {{ $errors->count() === 1 ? 'isian' : $errors->count().' isian' }} yang ditandai.</p>
                    </div>
                @endif
            </div>

            <div class="grid gap-5 border-t border-gray-200 px-5 py-6 sm:grid-cols-2 sm:px-8">
                <div class="sm:col-span-2">
                    <label for="nama" class="{{ $label }}">Nama</label>
                    <input id="nama" name="nama" type="text" required maxlength="150" value="{{ old('nama', $pengguna->nama) }}" class="{{ $kontrol('nama') }}" {!! $gagal('nama') !!}>
                    @error('nama') <p id="nama-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="username" class="{{ $label }}">Nama login</label>
                    <input id="username" name="username" type="text" required maxlength="50" autocomplete="off" value="{{ old('username', $pengguna->username) }}"
                        aria-describedby="username-bantuan" class="{{ $kontrol('username') }} font-mono" {!! $gagal('username') !!}>
                    <p id="username-bantuan" class="{{ $bantuan }}">Huruf, angka, tanda hubung, atau garis bawah.</p>
                    @error('username') <p id="username-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="{{ $label }}">Sandi</label>
                    <input id="password" name="password" type="password" @if ($baru) required @endif autocomplete="new-password"
                        aria-describedby="password-bantuan" class="{{ $kontrol('password') }}" {!! $gagal('password') !!}>
                    <p id="password-bantuan" class="{{ $bantuan }}">Minimal 8 karakter.{{ $baru ? '' : ' Kosongkan bila tidak diubah.' }}</p>
                    @error('password') <p id="password-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="peran" class="{{ $label }}">Level</label>
                    @if ($sendiri)
                        {{-- Select nonaktif tidak ikut terkirim; nilainya dikirim lewat input tersembunyi. --}}
                        <input type="hidden" name="peran" value="{{ $pengguna->peran->value }}">
                    @endif
                    <select id="peran" @unless ($sendiri) name="peran" @endunless required @disabled($sendiri) @if ($sendiri) aria-describedby="peran-bantuan" @endif
                        class="{{ $kontrol('peran') }} disabled:cursor-not-allowed disabled:bg-gray-100" {!! $gagal('peran') !!}>
                        @foreach (App\Enums\PeranPengguna::cases() as $p)
                            <option value="{{ $p->value }}" @selected($peran === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                    @if ($sendiri) <p id="peran-bantuan" class="{{ $bantuan }}">Level akun Anda sendiri tidak bisa diubah.</p> @endif
                    @error('peran') <p id="peran-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div data-untuk="admin_walidata">
                    <label for="walidata_id" class="{{ $label }}">Walidata</label>
                    <select id="walidata_id" name="walidata_id" class="{{ $kontrol('walidata_id') }}" {!! $gagal('walidata_id') !!}>
                        <option value="">Pilih walidata</option>
                        @foreach ($daftarWalidata as $id => $nama)
                            <option value="{{ $id }}" @selected((string) old('walidata_id', $pengguna->walidata_id) === (string) $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                    @error('walidata_id') <p id="walidata_id-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div data-untuk="petugas_pos">
                    <label for="pos_id" class="{{ $label }}">POS</label>
                    <select id="pos_id" name="pos_id" class="{{ $kontrol('pos_id') }}" aria-describedby="pos-bantuan" {!! $gagal('pos_id') !!}>
                        <option value="">Pilih pos</option>
                        @foreach ($daftarPos as $p)
                            <option value="{{ $p->id }}" @selected((string) old('pos_id', $pengguna->pos_id) === (string) $p->id)>{{ $p->nama }} ({{ $p->jenis?->label() }})</option>
                        @endforeach
                    </select>
                    <p id="pos-bantuan" class="{{ $bantuan }}">Walidata petugas mengikuti walidata pos.</p>
                    @error('pos_id') <p id="pos_id-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="{{ $label }}">Email</label>
                    <input id="email" name="email" type="email" maxlength="150" value="{{ old('email', $pengguna->email) }}" class="{{ $kontrol('email') }}" {!! $gagal('email') !!}>
                    @error('email') <p id="email-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="telepon" class="{{ $label }}">Telpon</label>
                    <input id="telepon" name="telepon" type="tel" maxlength="50" value="{{ old('telepon', $pengguna->telepon) }}" class="{{ $kontrol('telepon') }} tabular-nums" {!! $gagal('telepon') !!}>
                    @error('telepon') <p id="telepon-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="{{ $label }}">Status</span>
                    <label class="flex min-h-11 items-center gap-2.5 lg:min-h-10 {{ $sendiri ? 'cursor-not-allowed opacity-70' : 'cursor-pointer' }}">
                        <input type="checkbox" name="aktif" value="1" role="switch" class="peer sr-only" @checked(old('aktif', $pengguna->aktif)) @disabled($sendiri)>
                        <span class="relative h-6 w-11 shrink-0 rounded-full bg-gray-450 transition-colors peer-checked:bg-primary-500 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-500 motion-reduce:transition-none
                            after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-xs after:transition-transform peer-checked:after:translate-x-5 motion-reduce:after:transition-none" aria-hidden="true"></span>
                        <span class="hidden peer-checked:inline" aria-hidden="true">Aktif, bisa masuk</span>
                        <span class="text-gray-600 peer-checked:hidden" aria-hidden="true">Nonaktif, tidak bisa masuk</span>
                    </label>
                    @if ($sendiri) <p class="{{ $bantuan }}">Akun Anda sendiri tidak bisa dinonaktifkan.</p> @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 rounded-b-xl border-t border-gray-200 px-5 py-4 sm:px-8">
                <a href="{{ route('admin.pengguna') }}" class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md border border-gray-450 bg-white px-3.5 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 sm:flex-none lg:min-h-9">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Batal
                </a>
                <button class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md bg-primary-500 px-4 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 sm:flex-none lg:min-h-9">
                    <i class="bi bi-check2" aria-hidden="true"></i> {{ $baru ? 'Simpan pengguna' : 'Simpan perubahan' }}
                </button>
            </div>
        </form>
    </div>

    @push('skrip')
        <script>
            // Tampilkan isian walidata/pos sesuai level.
            const peran = document.getElementById('peran');
            const sesuaikan = () => document.querySelectorAll('[data-untuk]').forEach(el => { el.hidden = el.dataset.untuk !== peran.value; });
            peran.addEventListener('change', sesuaikan);
            sesuaikan();
        </script>
    @endpush
</x-layouts.admin>

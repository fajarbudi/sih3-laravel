{{--
    Daftar pengguna (aplikasi lama: vreferensi.php untuk Pengguna): Nama, Nama Login, Walidata, POS, Email, Telpon, Level,
    ditambah Status (aktif/nonaktif). Urut nama.
--}}
@php
    $pil = 'min-h-11 rounded-md border border-gray-200 bg-white px-2 text-sm text-gray-900 hover:bg-gray-50 focus:border-primary-500 focus:outline-none lg:min-h-7';
    $sel = 'border border-gray-200 px-2.5 py-2';
@endphp

<x-layouts.admin judul="Pengguna" induk="Pengaturan" :penuh="true">
    <x-slot:aksi>
        <a href="{{ route('admin.pengguna.create') }}"
            class="flex min-h-11 items-center gap-1.5 rounded-md bg-primary-500 px-2.5 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 lg:min-h-7">
            <i class="bi bi-plus-lg text-xs" aria-hidden="true"></i> Tambah pengguna
        </a>
    </x-slot:aksi>

    <x-slot:filter>
        <form method="GET" class="flex flex-1 flex-wrap items-center gap-2">
            <i class="bi bi-funnel text-gray-600" aria-hidden="true"></i>
            <label for="peran" class="sr-only">Level</label>
            <select id="peran" name="peran" onchange="this.form.requestSubmit()" class="{{ $pil }}">
                <option value="">Semua level</option>
                @foreach (App\Enums\PeranPengguna::cases() as $p)
                    <option value="{{ $p->value }}" @selected(($filter['peran'] ?? null) === $p->value)>{{ $p->label() }}</option>
                @endforeach
            </select>
            <label for="walidata" class="sr-only">Walidata</label>
            <select id="walidata" name="walidata" onchange="this.form.requestSubmit()" class="{{ $pil }} max-w-48">
                <option value="">Semua walidata</option>
                @foreach ($daftarWalidata as $id => $nama)
                    <option value="{{ $id }}" @selected(($filter['walidata'] ?? null) == $id)>{{ $nama }}</option>
                @endforeach
            </select>
            <noscript><button class="{{ $pil }}">Terapkan</button></noscript>
            @if ($filter)
                <a href="{{ route('admin.pengguna') }}" class="flex min-h-11 items-center gap-1 rounded-md px-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 lg:min-h-7">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Hapus filter
                </a>
            @endif
            <p class="text-gray-600 tabular-nums">{{ number_format($data->total(), 0, ',', '.') }} pengguna</p>
            <label for="cari" class="sr-only">Cari nama atau nama login</label>
            <div class="relative ml-auto max-sm:w-full">
                <i class="bi bi-search pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-gray-600" aria-hidden="true"></i>
                <input id="cari" name="cari" type="search" value="{{ $filter['cari'] ?? '' }}" placeholder="Cari nama / login" maxlength="100" onchange="this.form.requestSubmit()" class="{{ $pil }} w-full pl-7 sm:w-48">
            </div>
        </form>
    </x-slot:filter>

    @if ($data->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-people text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">Tidak ada pengguna untuk filter ini</p>
            <p class="mt-1 text-gray-600">Coba ubah atau hapus filter.</p>
        </div>
    @else
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">Pengguna panel admin, urut nama</caption>
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" class="{{ $sel }} font-medium">Nama</th>
                        <th scope="col" class="{{ $sel }} font-medium">Nama Login</th>
                        <th scope="col" class="{{ $sel }} font-medium">Walidata</th>
                        <th scope="col" class="{{ $sel }} font-medium">POS</th>
                        <th scope="col" class="{{ $sel }} font-medium">Email</th>
                        <th scope="col" class="{{ $sel }} font-medium">Telpon</th>
                        <th scope="col" class="{{ $sel }} font-medium">Level</th>
                        <th scope="col" class="{{ $sel }} font-medium">Status</th>
                        <th scope="col" class="{{ $sel }} w-px"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $u)
                        <tr class="hover:bg-gray-50">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $data->firstItem() + $loop->index }}.</td>
                            <td class="{{ $sel }} min-w-40">{{ $u->nama }}</td>
                            <td class="{{ $sel }} font-mono text-xs">{{ $u->username }}</td>
                            <td class="{{ $sel }} min-w-32">{{ $u->walidata?->nama }}</td>
                            <td class="{{ $sel }} min-w-32">{{ $u->pos?->nama }}</td>
                            <td class="{{ $sel }}">{{ $u->email }}</td>
                            <td class="{{ $sel }} whitespace-nowrap tabular-nums">{{ $u->telepon }}</td>
                            <td class="{{ $sel }} whitespace-nowrap">{{ $u->peran->label() }}</td>
                            <td class="{{ $sel }} whitespace-nowrap {{ $u->aktif ? '' : 'font-medium text-red-700' }}">{{ $u->aktif ? 'Aktif' : 'Nonaktif' }}</td>
                            <td class="{{ $sel }} px-1.5 py-1">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('admin.pengguna.edit', $u) }}" title="Ubah" aria-label="Ubah {{ $u->nama }}"
                                        class="flex size-11 items-center justify-center rounded-md text-primary-600 hover:bg-primary-50 active:bg-primary-100 lg:size-8">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    @unless ($u->is(auth()->user()))
                                        <form method="POST" action="{{ route('admin.pengguna.destroy', $u) }}" data-konfirmasi="Akun {{ $u->nama }} ({{ $u->username }}) akan dihapus dan tidak bisa masuk lagi.">
                                            @csrf @method('DELETE')
                                            <button title="Hapus" aria-label="Hapus {{ $u->nama }}" class="flex size-11 items-center justify-center rounded-md text-red-700 hover:bg-red-50 active:bg-red-100 lg:size-8">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endunless
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

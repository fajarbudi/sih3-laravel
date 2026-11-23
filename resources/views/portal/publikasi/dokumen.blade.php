{{--
    Publikasi > Dokumen (PublikasiController@dokumen), pengganti info/publikasi lama. Tampilan sama dengan Informasi H3
    (permintaan user): jumlah di kanan judul, pilihan jenis (tautan), cari cepat, satu tabel No./Jenis/Nama dokumen/Tahun
    + Lihat & Unduh. Klik baris membuka PDF di jendela; di ponsel hanya No. + nama (jenis & tahun di bawah nama).
--}}
@php
    $chip = 'inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold';
    $chipBiasa = "$chip bg-gray-100 text-gray-800 hover:bg-gray-200/70";
    $chipAktif = "$chip bg-primary-600 text-white shadow-md shadow-primary-600/25";
    // Tahun di data lama hampir selalu kosong: kolom hanya tampil bila ada dokumen bertahun.
    $adaTahun = $dokumen->contains(fn ($d) => filled($d->tahun));
@endphp

<x-layouts.portal judul="Dokumen" :hero-gelap="true" deskripsi="Peraturan, prosedur, dan dokumen pengelolaan hidrologi Wilayah Sungai Serayu Opak.">
    <x-portal.hero label="Publikasi" judul="Dokumen">Peraturan, prosedur, dan dokumen pengelolaan hidrologi Wilayah Sungai Serayu Opak.</x-portal.hero>

    <x-portal.lembar label="Publikasi" ikon="bi-file-earmark-text" judul="Dokumen & peraturan" keterangan="Klik baris dokumen untuk melihatnya, atau unduh dalam format PDF.">
        <x-slot:kanan>
            <p aria-live="polite" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200">
                Menampilkan <strong id="jumlah-dokumen" class="font-bold tabular-nums text-gray-950">{{ $dokumen->count() }}</strong> dokumen
            </p>
        </x-slot:kanan>

        {{-- Pilihan jenis (tautan) --}}
        <nav aria-label="Jenis dokumen" class="mt-6 flex flex-wrap gap-2">
            <a href="{{ route('portal.dokumen') }}" @if (! $aktif) aria-current="page" @endif class="{{ $aktif ? $chipBiasa : $chipAktif }}">
                <i class="bi bi-grid" aria-hidden="true"></i>Semua <span class="tabular-nums opacity-80">{{ $jumlahSemua }}</span>
            </a>
            @foreach ($kategori as $k)
                <a href="{{ route('portal.dokumen', ['jenis' => $k->slug]) }}" @if ($aktif?->is($k)) aria-current="page" @endif class="{{ $aktif?->is($k) ? $chipAktif : $chipBiasa }}">
                    {{ $k->nama }} <span class="tabular-nums opacity-80">{{ $jumlahPerJenis[$k->id] ?? 0 }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Cari cepat di peramban --}}
        <div class="mt-3 flex">
            <label class="flex h-10 w-full items-center gap-2 rounded-full bg-gray-100 px-4 ring-1 ring-inset ring-transparent focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500 sm:ml-auto sm:w-72">
                <i class="bi bi-search text-gray-600" aria-hidden="true"></i>
                <span class="sr-only">Cari dokumen</span>
                <input type="search" id="cari-dokumen" placeholder="Cari dokumen, jenis, tahun…" autocomplete="off" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0">
            </label>
        </div>

        {{-- Daftar dokumen --}}
        <div class="relative mt-5 overflow-x-auto rounded-3xl bg-white shadow-sm ring-1 ring-gray-200">
            <table class="w-full text-left text-sm sm:min-w-[52rem]">
                <caption class="sr-only">Daftar dokumen dan peraturan</caption>
                <thead class="whitespace-nowrap bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-600">
                    <tr>
                        <th scope="col" class="w-px px-3 py-3.5 text-center sm:px-5">No.</th>
                        <th scope="col" class="hidden px-4 py-3.5 sm:table-cell">Jenis</th>
                        <th scope="col" class="px-4 py-3.5">Nama dokumen</th>
                        @if ($adaTahun) <th scope="col" class="hidden px-4 py-3.5 sm:table-cell">Tahun</th> @endif
                        <th scope="col" class="hidden px-4 py-3.5 sm:table-cell"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody id="daftar-dokumen" class="divide-y divide-gray-100">
                    @forelse ($dokumen as $d)
                        @php $berkas = $d->lampiran->first(); @endphp
                        <tr data-dokumen class="transition-colors hover:bg-langit {{ $berkas ? 'cursor-pointer' : '' }}">
                            <td class="px-3 py-4 text-center align-top tabular-nums text-gray-600 sm:px-5 sm:align-middle" data-no>{{ $loop->iteration }}.</td>
                            <td class="hidden min-w-44 max-w-64 px-4 py-4 font-semibold text-gray-700 sm:table-cell">{{ $d->kategori?->nama ?? '—' }}</td>
                            <td class="py-4 pr-4 sm:px-4">
                                <div class="flex items-start gap-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-lg text-red-700 ring-1 ring-red-100">
                                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-w-0">
                                        @if ($berkas)
                                            {{-- Tombol = cara utama (papan ketik/pembaca layar) membuka dokumen; klik baris hanyalah pintasan. --}}
                                            <button type="button" data-jendela="dok-{{ $d->id }}" data-judul="{{ $d->nama }}" class="text-left font-bold text-gray-950 hover:text-primary-700">{{ $d->nama }}</button>
                                        @else
                                            <p class="font-bold text-gray-950">{{ $d->nama }}</p>
                                        @endif
                                        <p class="mt-1 text-[13px] text-gray-600 sm:hidden">{{ collect([$d->kategori?->nama, $d->tahun])->filter()->join(' · ') }}</p>
                                    </div>
                                </div>
                            </td>
                            @if ($adaTahun) <td class="hidden px-4 py-4 tabular-nums text-gray-800 sm:table-cell">{{ $d->tahun ?: '—' }}</td> @endif
                            <td class="hidden px-4 py-4 sm:table-cell">
                                @if ($berkas)
                                    <div class="flex justify-end gap-2 whitespace-nowrap">
                                        <button type="button" data-jendela="dok-{{ $d->id }}" data-judul="{{ $d->nama }}" tabindex="-1" aria-hidden="true" class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-white px-3.5 text-[13px] font-bold text-gray-800 ring-1 ring-gray-200 hover:text-primary-700 hover:ring-primary-300">
                                            <i class="bi bi-eye" aria-hidden="true"></i>Lihat
                                        </button>
                                        <a href="{{ $berkas->url() }}" target="_blank" rel="noopener" download class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-primary-600 px-3.5 text-[13px] font-bold text-white hover:bg-primary-700">
                                            <i class="bi bi-download" aria-hidden="true"></i>Unduh<span class="sr-only"> {{ $d->nama }}</span>
                                        </a>
                                    </div>
                                    <template id="dok-{{ $d->id }}">
                                        <iframe src="{{ $berkas->url() }}" title="{{ $d->nama }}" class="h-[70vh] w-full rounded-2xl bg-gray-100 ring-1 ring-gray-200"></iframe>
                                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                                            <a href="{{ $berkas->url() }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-gray-100 px-4 text-sm font-bold text-gray-800 hover:bg-gray-200">
                                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>Buka di tab baru
                                            </a>
                                            <a href="{{ $berkas->url() }}" download class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700">
                                                <i class="bi bi-download" aria-hidden="true"></i>Unduh
                                            </a>
                                        </div>
                                    </template>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-gray-600">Belum ada dokumen yang diterbitkan.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p id="cari-kosong" hidden class="px-5 py-12 text-center text-gray-600">Tidak ada dokumen yang cocok dengan pencarian.</p>
        </div>
    </x-portal.lembar>

    <x-portal.jendela lebar="w-[min(72rem,calc(100%-2rem))]" />

    @push('skrip')
        <script>
            const baris = [...document.querySelectorAll('#daftar-dokumen tr[data-dokumen]')];

            // Klik di mana saja pada baris = buka dokumen (kecuali tombol/tautan, yang menangani klik sendiri).
            document.getElementById('daftar-dokumen').addEventListener('click', e => {
                if (e.target.closest('a, button') || getSelection().toString()) return;
                e.target.closest('tr')?.querySelector('[data-jendela]')?.click();
            });

            // Pencarian cepat: menyaring baris, nomor diurutkan ulang.
            document.getElementById('cari-dokumen').addEventListener('input', e => {
                const q = e.target.value.trim().toLowerCase();
                let n = 0;
                baris.forEach(tr => {
                    tr.hidden = q && !tr.textContent.toLowerCase().includes(q);
                    if (!tr.hidden) tr.querySelector('[data-no]').textContent = ++n + '.';
                });
                document.getElementById('jumlah-dokumen').textContent = n;
                document.getElementById('cari-kosong').hidden = n > 0 || !baris.length;
            });
        </script>
    @endpush
</x-layouts.portal>

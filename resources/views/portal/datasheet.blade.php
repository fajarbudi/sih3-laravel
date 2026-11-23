{{--
    Informasi H3 per bidang (PortalController@datasheet), pengganti datasheet/{hidrologi|hidrometeorologi|hidrogeologi} lama.
    Isi & susunan mengikuti halaman lama: hero berjudul "Informasi <bidang>" + definisi, daftar pos (No., Walidata, Nama pos +
    jenis + lokasi, DAS/WS, Detail & Arsip), pilihan jenis, pencarian cepat, filter. Klik baris membuka jendela detail
    bertab Detail pos (route portal.pos.ringkas, isi sama dengan peta) dan Arsip data (route portal.pos.arsip),
    dengan tombol Sebelumnya/Berikutnya seperti panel samping lama. Gaya disamakan dengan beranda.
--}}
@php
    // Nama jenis = label peta, kecuali AGWLR yang di datasheet lama tampil sebagai jenis sendiri (ikon sama dengan MAT).
    $namaJenis = collect(App\Http\Controllers\PortalController::KELOMPOK)->map(fn ($k) => $k[0])->put('agwlr', 'AGWLR');
    $berkasIkon = ['awlr' => 'dugaair', 'kualitas_air' => 'kualitasair', 'arr' => 'curahhujan', 'klimatologi' => 'klimatologi', 'mat' => 'mat', 'agwlr' => 'mat'];
    $ikon = fn ($jenis) => asset("images/portal/penanda/{$berkasIkon[$jenis]}.png");
    $angka = fn ($n) => number_format($n, 0, ',', '.');
    $jenisAktif = request('jenis');
    $label = 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider ring-1 ring-inset';
    $chip = 'inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold';
    $chipBiasa = "$chip bg-gray-100 text-gray-800 hover:bg-gray-200/70";
    $chipAktif = "$chip bg-primary-600 text-white shadow-md shadow-primary-600/25";
    $kontrol = 'h-10 rounded-full border-0 bg-gray-100 px-4 text-sm font-semibold text-gray-800 ring-1 ring-inset ring-transparent focus:bg-white focus:ring-2 focus:ring-primary-500';
    $adaFilter = request()->hasAny(['jenis', 'walidata', 'das']);
@endphp

<x-layouts.portal :judul="'Informasi '.$tipe->label()" :deskripsi="$definisi" :hero-gelap="true">
    <x-portal.hero label="Informasi H3" :judul="$tipe->label()">{{ $definisi }}</x-portal.hero>

    <section aria-labelledby="judul-daftar" class="relative -mt-10 rounded-t-[2rem] bg-white pb-16 pt-10 sm:-mt-14 sm:rounded-t-[2.5rem] lg:pt-14">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <span class="{{ $label }} bg-primary-50 text-primary-700 ring-primary-100"><i class="bi bi-table" aria-hidden="true"></i>Datasheet</span>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="judul-daftar" class="mt-3 text-3xl font-extrabold tracking-tight text-gray-950 sm:text-4xl">Daftar pos {{ strtolower($tipe->label()) }}</h2>
                    <p class="mt-2 text-gray-600">Klik baris pos untuk melihat detail dan arsip datanya.</p>
                </div>
                <p aria-live="polite" class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm text-gray-700 shadow-sm ring-1 ring-gray-200">
                    Menampilkan <strong id="jumlah-pos" class="font-bold tabular-nums text-gray-950">{{ $angka($pos->count()) }}</strong> pos
                </p>
            </div>

            {{-- Pilihan jenis (tautan, seperti chip lama) --}}
            <nav aria-label="Jenis pos" class="mt-6 flex flex-wrap gap-2">
                <a href="{{ request()->fullUrlWithoutQuery('jenis') }}" @if (! $jenisAktif) aria-current="page" @endif class="{{ $jenisAktif ? $chipBiasa : $chipAktif }}">
                    <i class="bi bi-grid" aria-hidden="true"></i>Semua <span class="tabular-nums opacity-80">{{ $angka($jumlahSemua) }}</span>
                </a>
                @foreach ($jumlahPerJenis as $kel => $n)
                    <a href="{{ request()->fullUrlWithQuery(['jenis' => $kel]) }}" @if ($jenisAktif === $kel) aria-current="page" @endif class="{{ $jenisAktif === $kel ? $chipAktif : $chipBiasa }}">
                        <img src="{{ $ikon($kel) }}" alt="" width="18" height="21" class="h-[21px] w-[18px]">{{ $namaJenis[$kel] }} <span class="tabular-nums opacity-80">{{ $angka($n) }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Filter walidata & DAS (dikirim ke server, otomatis saat dipilih) + cari cepat di peramban --}}
            <form method="get" class="mt-3 flex flex-wrap items-center gap-2">
                @if ($jenisAktif) <input type="hidden" name="jenis" value="{{ $jenisAktif }}"> @endif
                <label for="f-walidata" class="sr-only">Walidata</label>
                <select id="f-walidata" name="walidata" onchange="this.form.submit()" class="{{ $kontrol }} max-w-full sm:max-w-64">
                    <option value="">Semua walidata</option>
                    @foreach ($pilihanWalidata as $id => $nama)
                        <option value="{{ $id }}" @selected(request('walidata') == $id)>{{ $nama }}</option>
                    @endforeach
                </select>
                <label for="f-das" class="sr-only">Daerah aliran sungai</label>
                <select id="f-das" name="das" onchange="this.form.submit()" class="{{ $kontrol }} max-w-full sm:max-w-56">
                    <option value="">Semua DAS</option>
                    @foreach ($pilihanDas as $das)
                        <option value="{{ $das }}" @selected(request('das') === $das)>DAS {{ $das }}</option>
                    @endforeach
                </select>
                <noscript><button class="{{ $chipBiasa }}">Tampilkan</button></noscript>
                @if ($adaFilter)
                    <a href="{{ route('portal.datasheet', $tipe) }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-red-700 hover:bg-red-50">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>Hapus filter
                    </a>
                @endif
                <label class="flex h-10 w-full items-center gap-2 rounded-full bg-gray-100 px-4 ring-1 ring-inset ring-transparent focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500 sm:ml-auto sm:w-72">
                    <i class="bi bi-search text-gray-600" aria-hidden="true"></i>
                    <span class="sr-only">Cari pos</span>
                    <input type="search" id="cari-pos" placeholder="Cari pos, walidata, lokasi…" autocomplete="off" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:ring-0">
                </label>
            </form>

            {{-- Daftar pos --}}
            <div class="relative mt-5 overflow-x-auto rounded-3xl bg-white shadow-sm ring-1 ring-gray-200">
                <table class="w-full text-left text-sm sm:min-w-[52rem]">
                    <caption class="sr-only">Daftar pos {{ strtolower($tipe->label()) }}</caption>
                    <thead class="whitespace-nowrap bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-600">
                        <tr>
                            <th scope="col" class="w-px px-3 py-3.5 text-center sm:px-5">No.</th>
                            <th scope="col" class="hidden px-4 py-3.5 sm:table-cell">Walidata</th>
                            <th scope="col" class="px-4 py-3.5">Nama pos</th>
                            <th scope="col" class="hidden px-4 py-3.5 sm:table-cell">DAS / Wilayah sungai</th>
                            <th scope="col" class="hidden px-4 py-3.5 sm:table-cell"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody id="daftar-pos" class="divide-y divide-gray-100">
                        @forelse ($pos as $p)
                            @php
                                $kel = $p->jenis->value;
                                $lokasi = collect([$p->desa, $p->kecamatan, $p->kabupaten, $p->provinsi])->filter()->join(', ');
                                $tersedia = collect([$p->data_tersedia_dari, $p->data_tersedia_sampai])->filter()->unique()->join('–');
                            @endphp
                            <tr data-pos="{{ $p->id }}" data-nama="{{ $p->nama }}" data-jenis="{{ $namaJenis[$kel] }}" data-ikon="{{ $ikon($kel) }}"
                                class="cursor-pointer transition-colors hover:bg-langit">
                                <td class="px-3 py-4 text-center align-top tabular-nums text-gray-600 sm:px-5 sm:align-middle" data-no>{{ $loop->iteration }}.</td>
                                <td class="hidden min-w-44 max-w-56 px-4 py-4 font-semibold text-gray-700 sm:table-cell">{{ $p->walidata?->nama ?? '—' }}</td>
                                <td class="py-4 pr-4 sm:px-4">
                                    <div class="flex items-start gap-3">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 ring-1 ring-primary-100">
                                            <img src="{{ $ikon($kel) }}" alt="" width="20" height="23" class="h-[23px] w-5">
                                        </span>
                                        <div class="min-w-0">
                                            {{-- Tombol = cara utama (papan ketik/pembaca layar) membuka detail; klik baris hanyalah pintasan. --}}
                                            <button type="button" data-buka="detail" class="text-left font-bold text-gray-950 hover:text-primary-700">{{ $p->nama }}</button>
                                            <span class="ml-1 inline-block rounded-md bg-primary-50 px-2 py-0.5 text-xs font-bold text-primary-700">{{ $namaJenis[$kel] }}</span>
                                            <p class="mt-1 text-[13px] font-semibold text-gray-700 sm:hidden">{{ $p->walidata?->nama }}</p>
                                            @if ($lokasi)
                                                <p class="mt-1 flex items-start gap-1 text-[13px] text-gray-600"><i class="bi bi-geo-alt mt-px" aria-hidden="true"></i>{{ $lokasi }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden px-4 py-4 text-[13px] leading-relaxed text-gray-800 sm:table-cell">
                                    @if ($p->das || $p->wilayah_sungai)
                                        @if ($p->das) <span class="inline-block w-8 text-[11px] font-bold text-gray-500">DAS</span>{{ $p->das }}<br> @endif
                                        @if ($p->wilayah_sungai) <span class="inline-block w-8 text-[11px] font-bold text-gray-500">WS</span>{{ $p->wilayah_sungai }} @endif
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="hidden px-4 py-4 sm:table-cell">
                                    <div class="flex justify-end gap-2 whitespace-nowrap">
                                        <button type="button" data-buka="detail" tabindex="-1" aria-hidden="true" class="inline-flex min-h-9 items-center gap-1.5 rounded-full bg-primary-600 px-3.5 text-[13px] font-bold text-white hover:bg-primary-700">
                                            <i class="bi bi-info-circle" aria-hidden="true"></i>Detail
                                        </button>
                                        <button type="button" data-buka="arsip" class="inline-flex min-h-9 w-32 items-center justify-center gap-1.5 rounded-full bg-white px-3 text-[13px] font-bold text-gray-800 ring-1 ring-gray-200 hover:text-primary-700 hover:ring-primary-300">
                                            <i class="bi bi-archive" aria-hidden="true"></i>{{ $tersedia ?: 'Arsip' }}<span class="sr-only"> arsip data {{ $p->nama }}</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-600">Tidak ada pos untuk filter ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p id="cari-kosong" hidden class="px-5 py-12 text-center text-gray-600">Tidak ada pos yang cocok dengan pencarian.</p>
            </div>
        </div>
    </section>

    {{-- Jendela detail: gaya sama dengan jendela detail pos di peta, ditambah tab dan tombol sebelumnya/berikutnya. --}}
    <dialog id="detail-pos" aria-labelledby="detail-pos-judul"
        data-detail="{{ route('portal.pos.ringkas', '__ID__') }}" data-arsip="{{ route('portal.pos.arsip', '__ID__') }}"
        class="m-auto max-h-[calc(100%-2rem)] w-[min(56rem,calc(100%-2rem))] overflow-hidden rounded-3xl bg-white p-0 text-gray-900 shadow-2xl backdrop:bg-gray-950/50 backdrop:backdrop-blur-sm">
        <div class="flex max-h-[calc(100vh-2rem)] flex-col">
            <div class="flex items-center gap-3.5 px-5 pt-5 sm:px-7 sm:pt-6">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-50 ring-1 ring-primary-100">
                    <img id="detail-pos-ikon" src="" alt="" width="28" height="32" class="h-8 w-7">
                </span>
                <div class="min-w-0 flex-1">
                    <p id="detail-pos-jenis" class="text-xs font-bold uppercase tracking-wider text-primary-600"></p>
                    <h2 id="detail-pos-judul" class="mt-0.5 text-xl font-extrabold leading-snug tracking-tight text-gray-950 sm:text-2xl"></h2>
                </div>
                <button type="button" data-tutup aria-label="Tutup" class="-mr-1 flex size-10 shrink-0 items-center justify-center self-start rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 hover:text-gray-950">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <div role="tablist" aria-label="Isi detail pos" class="mx-5 mt-4 flex gap-1 rounded-full bg-gray-100 p-1 sm:mx-7 sm:w-fit">
                @foreach (['detail' => ['Detail pos', 'bi-info-circle'], 'arsip' => ['Arsip data', 'bi-archive']] as $tab => [$teks, $ik])
                    <button type="button" role="tab" data-tab="{{ $tab }}" aria-selected="false" aria-controls="detail-pos-isi"
                        class="inline-flex min-h-10 flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-full px-4 text-sm font-semibold text-gray-700 hover:text-gray-950 aria-selected:bg-white aria-selected:text-primary-700 aria-selected:shadow-sm">
                        <i class="bi {{ $ik }}" aria-hidden="true"></i>{{ $teks }}
                    </button>
                @endforeach
            </div>
            <div id="detail-pos-isi" role="tabpanel" class="min-h-48 overflow-y-auto px-5 pb-6 pt-5 sm:px-7"></div>
            <div class="flex items-center justify-between gap-3 border-t border-gray-100 px-5 py-3 sm:px-7">
                <button type="button" data-geser="-1" class="inline-flex min-h-10 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:hover:bg-transparent">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>Sebelumnya
                </button>
                <span id="detail-pos-urutan" class="text-sm tabular-nums text-gray-600"></span>
                <button type="button" data-geser="1" class="inline-flex min-h-10 items-center gap-1.5 rounded-full px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-40 disabled:hover:bg-transparent">
                    Berikutnya<i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </dialog>

    @push('skrip')
        <script>
            const dialog = document.getElementById('detail-pos');
            const isi = document.getElementById('detail-pos-isi');
            const baris = [...document.querySelectorAll('#daftar-pos tr[data-pos]')];
            const terlihat = () => baris.filter(tr => !tr.hidden);
            let aktif, tabAktif = 'detail', permintaan;
            const cache = {};

            function pesan(teks) {
                const p = document.createElement('p');
                p.className = 'py-12 text-center text-gray-600';
                p.textContent = teks;
                isi.replaceChildren(p);
            }

            function tampilTab(tab) {
                tabAktif = tab;
                dialog.querySelectorAll('[data-tab]').forEach(b => b.setAttribute('aria-selected', String(b.dataset.tab === tab)));
                const url = dialog.dataset[tab].replace('__ID__', aktif.dataset.pos);
                if (cache[url]) { isi.innerHTML = cache[url]; isi.scrollTop = 0; return; }
                pesan('Memuat…');
                permintaan?.abort();
                permintaan = new AbortController();
                // Isi = HTML Blade dari server kita sendiri (teks sudah di-escape).
                fetch(url, { signal: permintaan.signal, headers: { Accept: 'text/html' } })
                    .then(r => { if (!r.ok) throw new Error(r.status); return r.text(); })
                    .then(html => { cache[url] = html; isi.innerHTML = html; isi.scrollTop = 0; })
                    .catch(e => e.name !== 'AbortError' && pesan('Gagal memuat. Coba lagi.'));
            }

            function buka(tr, tab) {
                aktif?.classList.remove('bg-langit');
                aktif = tr;
                tr.classList.add('bg-langit');
                document.getElementById('detail-pos-judul').textContent = tr.dataset.nama;
                document.getElementById('detail-pos-jenis').textContent = tr.dataset.jenis;
                document.getElementById('detail-pos-ikon').src = tr.dataset.ikon;
                const daftar = terlihat(), i = daftar.indexOf(tr);
                document.getElementById('detail-pos-urutan').textContent = `${i + 1} / ${daftar.length}`;
                dialog.querySelector('[data-geser="-1"]').disabled = i <= 0;
                dialog.querySelector('[data-geser="1"]').disabled = i >= daftar.length - 1;
                if (!dialog.open) dialog.showModal();
                tampilTab(tab);
            }

            document.getElementById('daftar-pos').addEventListener('click', e => {
                const tr = e.target.closest('tr[data-pos]');
                if (tr && !getSelection().toString()) buka(tr, e.target.closest('[data-buka]')?.dataset.buka ?? 'detail');
            });
            dialog.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => tampilTab(b.dataset.tab)));
            dialog.querySelectorAll('[data-geser]').forEach(b => b.addEventListener('click', () => {
                const daftar = terlihat(), tujuan = daftar[daftar.indexOf(aktif) + Number(b.dataset.geser)];
                if (tujuan) { buka(tujuan, tabAktif); tujuan.scrollIntoView({ block: 'nearest' }); }
            }));
            dialog.querySelector('[data-tutup]').addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', e => e.target === dialog && dialog.close());
            dialog.addEventListener('close', () => { aktif?.classList.remove('bg-langit'); aktif?.querySelector('[data-buka]')?.focus(); });

            // Pencarian cepat: menyaring baris yang sudah dimuat, nomor diurutkan ulang.
            document.getElementById('cari-pos').addEventListener('input', e => {
                const q = e.target.value.trim().toLowerCase();
                let n = 0;
                baris.forEach(tr => {
                    tr.hidden = q && !tr.textContent.toLowerCase().includes(q);
                    if (!tr.hidden) tr.querySelector('[data-no]').textContent = ++n + '.';
                });
                document.getElementById('jumlah-pos').textContent = n.toLocaleString('id-ID');
                document.getElementById('cari-kosong').hidden = n > 0 || !baris.length;
            });
        </script>
    @endpush
</x-layouts.portal>

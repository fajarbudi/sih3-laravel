{{--
    Isi wadah peta (dipakai beranda dan halaman Pos Pemantauan layar penuh): elemen Leaflet, kotak cari desa, pesan,
    legenda. Logika di resources/js/peta.js. Pemanggil menyediakan wadah relative + isolate.
    Parameter: $layarPenuh (bool) = halaman peta layar penuh (gulir langsung zoom, posisi legenda berbeda).
--}}
@php
    $kelompok = App\Http\Controllers\PortalController::KELOMPOK;
    $ikon = fn ($nama) => asset("images/portal/penanda/$nama.png");
    $berkasIkon = ['awlr' => 'dugaair', 'kualitas_air' => 'kualitasair', 'arr' => 'curahhujan', 'klimatologi' => 'klimatologi', 'mat' => 'mat'];
    // Legenda status = legenda aplikasi lama (indeks = kelas di PortalController::KELAS).
    $legenda = [
        ['Curah hujan', 'curahhujan', [1 => ['Tidak hujan', '0 mm'], ['Hujan sangat ringan', '0,1 sampai 1 mm'], ['Hujan ringan', '1 sampai 5 mm'],
            ['Hujan sedang', '5 sampai 10 mm'], ['Hujan lebat', '10 sampai 20 mm'], ['Hujan sangat lebat', '20 mm atau lebih']]],
        ['Duga air', 'dugaair', [1 => ['Normal', ''], ['Waspada', ''], ['Siaga', ''], ['Awas', '']]],
        ['Kualitas air', 'kualitasair', [1 => ['Baik', 'PIj 1,0 atau kurang'], ['Cemar ringan', '1,0 sampai 5,0'], ['Cemar sedang', '5,0 sampai 10'], ['Cemar berat', 'lebih dari 10']]],
    ];
    // Beranda: legenda mendatar di tengah bawah (bawah peta = laut). Layar penuh: ponsel di kiri atas di bawah kotak cari,
    // layar lebar di bawah, di antara panel filter kiri dan tombol zoom kanan.
    $posisiLegenda = $layarPenuh
        ? 'left-3 top-[4.25rem] w-fit open:w-[min(24rem,calc(100%-1.5rem))] md:top-auto md:bottom-4 md:left-[24.5rem] md:right-24 md:mx-auto md:w-auto md:max-w-[46rem] md:open:w-auto'
        : 'bottom-4 left-1/2 w-[min(60rem,calc(100%-12rem))] -translate-x-1/2';
    $labelPeringatan = $layarPenuh ? 'Status terkini' : 'Pos peringatan';
@endphp
            <p id="peta-memuat" class="absolute inset-0 flex items-center justify-center text-gray-600">Memuat peta…</p>
            <div id="peta-leaflet" class="absolute inset-0" role="region" aria-label="Peta pos pemantauan"
                data-ikon="{{ asset('images/portal/penanda') }}" data-das="{{ asset('geojson/das.json') }}" data-detail="{{ route('portal.pos.ringkas', '__ID__') }}" data-desa="{{ asset('geojson/desa.json') }}" @if ($layarPenuh) data-layar-penuh @endif></div>

            {{-- Cari desa seperti portal live: pilih desa -> peta menampilkan pos dalam radius 10 km beserta jaraknya.
                 Data titik desa (public/geojson/desa.json, dari titik_desa.geojson lama) baru diunduh saat kotak difokus. --}}
            <div id="cari-desa" class="absolute inset-x-3 top-3 z-[550] hidden sm:left-auto sm:right-3 sm:w-80 [.js_&]:block">
                <label for="cari-desa-isian" class="sr-only">Cari desa, kecamatan, atau kabupaten</label>
                <div class="relative">
                    <i class="bi bi-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-500" aria-hidden="true"></i>
                    <input id="cari-desa-isian" type="text" role="combobox" aria-expanded="false" aria-controls="cari-desa-hasil" aria-autocomplete="list" autocomplete="off" enterkeyhint="search"
                        placeholder="Cari desa / kecamatan / kabupaten…" class="h-12 w-full rounded-full border-0 bg-white pl-11 pr-12 ring-1 ring-gray-950/10 text-sm text-gray-900 shadow-lg shadow-gray-950/10 placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <button type="button" id="cari-desa-hapus" hidden aria-label="Hapus pencarian" class="absolute right-1.5 top-1/2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <ul id="cari-desa-hasil" role="listbox" aria-label="Hasil pencarian desa" hidden class="mt-2 max-h-72 overflow-y-auto rounded-2xl bg-white p-1.5 text-sm shadow-xl shadow-gray-950/15 ring-1 ring-gray-950/5"></ul>
                <p id="cari-desa-info" hidden aria-live="polite" class="mt-2 rounded-2xl bg-white px-4 py-2.5 text-sm text-gray-700 shadow-lg shadow-gray-950/10 ring-1 ring-gray-950/5"></p>
            </div>

            <p id="peta-kosong" hidden class="absolute inset-x-4 top-28 z-[500] sm:top-4 mx-auto max-w-sm rounded-xl bg-white p-4 text-center text-sm text-gray-700 shadow-lg">Tidak ada pos yang cocok dengan filter. Ubah pilihan jenis atau walidata.</p>
            <p id="peta-galat" hidden class="absolute inset-x-4 bottom-12 z-[500] mx-auto max-w-sm rounded-xl bg-white p-4 text-center text-sm text-gray-700 shadow-lg">Sebagian ubin peta OpenStreetMap gagal dimuat. Pos tetap tampil.</p>
            <noscript><p class="absolute inset-0 flex items-center justify-center p-6 text-center text-gray-700">Peta membutuhkan JavaScript.</p></noscript>

            {{-- Legenda MENDATAR di tengah bawah peta (permintaan user): bagian bawah peta adalah laut, jadi tidak menutupi pos.
                 Lebar menyisakan ruang tombol Peta/Satelit di kiri dan zoom di kanan. Isi mengikuti mode (data-legenda, diatur peta.js).
                 Terbuka di layar lebar, tertutup di ponsel (diatur peta.js). --}}
            <details open id="legenda" class="group absolute {{ $posisiLegenda }} z-[500] max-h-[calc(100%-6rem)] overflow-y-auto md:max-h-[calc(100%-2rem)] rounded-3xl bg-white/95 text-sm shadow-xl shadow-gray-950/15 ring-1 ring-gray-950/5 backdrop-blur">
                <summary class="sticky top-0 flex min-h-11 list-none items-center justify-center gap-2 bg-white/95 px-4 font-bold text-gray-950 [&::-webkit-details-marker]:hidden">
                    <i class="bi bi-list-ul" aria-hidden="true"></i> Legenda
                    <i class="bi bi-chevron-up text-xs text-gray-600 group-open:rotate-180" aria-hidden="true"></i>
                </summary>
                <div class="border-t border-gray-100 px-4 pb-4 pt-3 sm:px-6">
                    <div data-legenda="semua" class="text-center">
                        <ul class="flex flex-wrap justify-center gap-x-6 gap-y-2">
                            @foreach ($kelompok as $kel => [$labelJenis])
                                <li class="flex items-center gap-2"><img src="{{ $ikon($berkasIkon[$kel]) }}" alt="" width="20" height="23" class="h-[23px] w-5 shrink-0"> <span class="text-gray-800">{{ $labelJenis }}</span></li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-xs text-gray-600">Warna ikon menunjukkan status terkini. Pilih <strong class="font-semibold text-gray-800">{{ $labelPeringatan }}</strong> untuk arti setiap warna.</p>
                    </div>
                    <div data-legenda="peringatan" hidden class="grid gap-x-8 gap-y-4 sm:grid-cols-3">
                        @foreach ($legenda as [$judul, $berkas, $kelas])
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-primary-700">{{ $judul }}</p>
                                <ul class="mt-1.5 space-y-1">
                                    @foreach ($kelas as $k => [$nama, $rentang])
                                        <li class="flex items-center gap-2">
                                            <img src="{{ $ikon("$berkas-$k") }}" alt="" width="18" height="21" class="h-[21px] w-[18px] shrink-0">
                                            <span class="flex-1 text-gray-800">{{ $nama }}</span>
                                            @if ($rentang) <span class="text-xs text-gray-600">{{ $rentang }}</span> @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                    <ul class="mt-3 flex flex-wrap justify-center gap-x-6 gap-y-1.5 border-t border-gray-100 pt-3 text-xs text-gray-700">
                        <li class="flex items-center gap-2"><span class="h-3.5 w-[18px] shrink-0 rounded-sm border-2 border-[#1d4ed8] bg-[#3b82f6]/10" aria-hidden="true"></span> Batas daerah aliran sungai (DAS)</li>
                    </ul>
                </div>
            </details>

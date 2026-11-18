{{--
    Kartu grafik. Judul = pertanyaan yang dijawab grafik. Setiap grafik wajib punya tabel data (slot "tabel"):
    jalur yang tidak bergantung pada warna atau hover, dan cadangan bila Chart.js gagal dimuat.
    Pakai: <x-admin.grafik id="..." judul="..." :kosong="$tidakAdaData" kosong-teks="...">
               <x-slot:tabel>...baris tabel...</x-slot:tabel>
           </x-admin.grafik>
--}}
@props(['id', 'judul', 'keterangan' => null, 'kosong' => false, 'kosongTeks' => 'Belum ada data.', 'tinggi' => 'h-60'])

<section {{ $attributes->class('flex flex-col rounded-lg border border-gray-200 bg-white') }}>
    <header class="px-4 pt-4">
        <h2 class="font-medium">{{ $judul }}</h2>
        @if ($keterangan)
            <p class="mt-0.5 text-gray-500">{{ $keterangan }}</p>
        @endif
    </header>

    @if ($kosong)
        <p class="flex flex-1 items-center justify-center px-4 py-14 text-center text-gray-500">{{ $kosongTeks }}</p>
    @else
        <div class="relative {{ $tinggi }} px-2 pb-2 pt-3">
            <canvas id="{{ $id }}" role="img" aria-label="{{ $judul }}. Data lengkap ada di tabel di bawah grafik."></canvas>
            <p class="grafik-gagal absolute inset-0 hidden items-center justify-center px-4 text-center text-gray-500">
                Grafik tidak dapat dimuat. Datanya tetap bisa dilihat di tabel di bawah.
            </p>
        </div>
        <details class="mt-auto border-t border-gray-200">
            <summary class="flex min-h-11 items-center px-4 text-gray-500 hover:bg-gray-50 hover:text-gray-900 lg:min-h-9">Lihat data dalam tabel</summary>
            <div class="max-h-72 overflow-auto px-4 pb-3">
                <table class="w-full text-left tabular-nums">
                    {{ $tabel }}
                </table>
            </div>
        </details>
    @endif
</section>

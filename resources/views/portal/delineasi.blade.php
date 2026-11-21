{{--
    Delineasi DTA: aplikasi terpisah (delineasi.bbwsserayuopak.id) ditampilkan dalam iframe selebar layar di bawah header
    portal (permintaan user), tanpa kaki halaman seperti halaman peta Pos Pemantauan.
    Situs itu tidak mengirim X-Frame-Options/CSP frame-ancestors (dicek 2026-11-22), jadi boleh dibingkai.
--}}
<x-layouts.portal judul="Delineasi DTA" :tanpa-kaki="true"
    deskripsi="Delineasi daerah tangkapan air (DTA) Wilayah Sungai Serayu Opak.">
    {{-- Tinggi = layar dikurangi header (termasuk garis bawah 1px), agar halaman tidak tergulir. --}}
    <iframe src="https://delineasi.bbwsserayuopak.id/" title="Aplikasi Delineasi DTA"
        class="block h-[calc(100dvh-4rem-1px)] w-full border-0 bg-gray-100 lg:h-[calc(100dvh-4.5rem-1px)]"
        allow="geolocation; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
</x-layouts.portal>

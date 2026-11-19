{{--
    Menu sidebar, mengikuti menu panel lama (dapur_template.php):
    - Superadmin (is_admin, level >= 7): semua menu.
    - Admin walidata (is_walidata, level >= 5): Dashboard, H3, Ketersediaan data, Catatan Kejadian.
    - Petugas pos (this_operator, level 3): input & rekap sesuai jenis posnya.
    Item yang route-nya belum dibuat tampil "Segera" (lihat x-admin.menu-tautan).
--}}
@php
    $pengguna = auth()->user();
    $superadmin = $pengguna->isSuperadmin();
    $petugas = $pengguna->peran === App\Enums\PeranPengguna::PetugasPos;
    $jenisPos = $pengguna->pos?->jenis?->value;
@endphp

<nav class="mt-2 flex-1 overflow-y-auto px-2 pb-3" aria-label="Menu utama">
    {{-- Dashboard --}}
    <p id="menu-dashboard" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">Dashboard</p>
    <ul class="space-y-px" aria-labelledby="menu-dashboard">
        <x-admin.menu-tautan route="admin.dashboard" ikon="house">Halaman Depan</x-admin.menu-tautan>
    </ul>

    @if ($petugas)
        {{-- Petugas pos: hanya jenis data posnya sendiri --}}
        @if ($jenisPos === 'awlr')
            <p id="menu-petugas" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">Hidrologi</p>
            <ul class="space-y-px" aria-labelledby="menu-petugas">
                <x-admin.menu-tautan route="admin.duga-air" ikon="droplet">Duga Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.debit" ikon="droplet">Debit</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.duga-air" ikon="table">Rekap Duga Air</x-admin.menu-tautan>
            </ul>
        @elseif ($jenisPos === 'kualitas_air')
            <p id="menu-petugas" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">Hidrologi</p>
            <ul class="space-y-px" aria-labelledby="menu-petugas">
                <x-admin.menu-tautan route="admin.kualitas-air" ikon="droplet">Kualitas Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.kualitas-air" ikon="table">Rekap Kualitas Air</x-admin.menu-tautan>
            </ul>
        @elseif ($jenisPos === 'arr')
            <p id="menu-petugas" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">Hidrometeorologi</p>
            <ul class="space-y-px" aria-labelledby="menu-petugas">
                <x-admin.menu-tautan route="admin.curah-hujan" ikon="cloud-rain">Curah Hujan</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.curah-hujan" ikon="table">Rekap Curah Hujan</x-admin.menu-tautan>
            </ul>
        @elseif ($jenisPos === 'klimatologi')
            <p id="menu-petugas" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">Hidrometeorologi</p>
            <ul class="space-y-px" aria-labelledby="menu-petugas">
                <x-admin.menu-tautan route="admin.klimatologi" ikon="cloud-rain">Klimatologi</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.klimatologi" ikon="table">Rekap Klimatologi</x-admin.menu-tautan>
            </ul>
        @elseif ($jenisPos === 'mat' || $jenisPos === 'agwlr')
            <p id="menu-petugas" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">Hidrogeologi</p>
            <ul class="space-y-px" aria-labelledby="menu-petugas">
                <x-admin.menu-tautan route="admin.muka-air-tanah" ikon="layers">Muka Air Tanah</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.muka-air-tanah" ikon="table">Rekap Muka Air Tanah</x-admin.menu-tautan>
            </ul>
        @endif
    @else
        {{-- H3 --}}
        <p id="menu-h3" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">H3</p>
        <ul class="space-y-px" aria-labelledby="menu-h3">
            <x-admin.menu-grup label="Hidrologi" ikon="droplet">
                <x-admin.menu-tautan route="admin.duga-air">Duga Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.kualitas-air">Kualitas Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.monitoring-duga-air">Monitoring Duga Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.duga-air">Rekap Duga Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.kualitas-air">Rekap Kualitas Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.duga-air-pjt">Duga Air PJT</x-admin.menu-tautan>
            </x-admin.menu-grup>
            <x-admin.menu-grup label="Hidrogeologi" ikon="layers">
                <x-admin.menu-tautan route="admin.muka-air-tanah">Muka Air Tanah</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.muka-air-tanah">Rekap Muka Air Tanah</x-admin.menu-tautan>
            </x-admin.menu-grup>
            <x-admin.menu-grup label="Hidrometeorologi" ikon="cloud-rain">
                <x-admin.menu-tautan route="admin.curah-hujan">Curah Hujan</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.klimatologi">Klimatologi</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.curah-hujan">Rekap Curah Hujan</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rekap.klimatologi">Rekap Klimatologi</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.curah-hujan-pjt">Curah Hujan PJT</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.curah-hujan-telemetri">Curah Hujan Telemetri</x-admin.menu-tautan>
            </x-admin.menu-grup>
        </ul>

        {{-- Ketersediaan data --}}
        <p id="menu-ketersediaan" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">Ketersediaan data</p>
        <ul class="space-y-px" aria-labelledby="menu-ketersediaan">
            <x-admin.menu-grup label="Unduh Data" ikon="download">
                <x-admin.menu-tautan route="admin.unduh.pengajuan">Pengajuan</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.unduh.verifikasi">Verifikasi</x-admin.menu-tautan>
            </x-admin.menu-grup>
            <x-admin.menu-grup label="Ketersediaan Data" ikon="archive">
                <x-admin.menu-tautan route="admin.arsip">File Arsip</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.ketersediaan.duga-air">Duga Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.ketersediaan.curah-hujan">Curah Hujan</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.ketersediaan.kualitas-air">Kualitas Air</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.ketersediaan.muka-air-tanah">Muka Air Tanah</x-admin.menu-tautan>
            </x-admin.menu-grup>
        </ul>
    @endif

    {{-- Pengaturan --}}
    <p id="menu-pengaturan" class="mb-0.5 mt-5 px-2 text-xs font-medium text-gray-600">Pengaturan</p>
    <ul class="space-y-px" aria-labelledby="menu-pengaturan">
        @if ($superadmin)
            <x-admin.menu-grup label="Publikasi" ikon="newspaper">
                <x-admin.menu-tautan route="admin.galeri">Galeri Pos</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.peta">Galeri Peta</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.glosarium">Glosarium</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.buletin">Buletin</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.album">Album</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.dokumen">Publikasi Dokumen</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.pengiriman-air">Penanganan Kekeringan</x-admin.menu-tautan>
            </x-admin.menu-grup>
        @endif

        <x-admin.menu-tautan route="admin.kejadian" ikon="journal-text">Catatan Kejadian</x-admin.menu-tautan>

        @if ($superadmin)
            <x-admin.menu-grup label="Pengaturan" ikon="sliders">
                <x-admin.menu-tautan route="admin.perangkat-telemetri">Pos Telemetri</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.batas-siaga">Ambang Batas</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.rumus-debit">Rumus Debit</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.pengguna">Pengguna</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.walidata">Walidata</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.pos">Pos</x-admin.menu-tautan>
            </x-admin.menu-grup>
            <x-admin.menu-grup label="Input" ikon="pencil-square">
                <x-admin.menu-tautan route="admin.input.curah-hujan">Curah Hujan</x-admin.menu-tautan>
                <x-admin.menu-tautan route="admin.input.debit">Debit</x-admin.menu-tautan>
            </x-admin.menu-grup>
        @endif
    </ul>
</nav>

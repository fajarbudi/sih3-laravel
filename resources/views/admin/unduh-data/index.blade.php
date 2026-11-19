{{--
    Daftar permintaan unduh data, dipakai untuk Pengajuan (permintaan milik pengguna) dan Verifikasi
    (permintaan atas pos walidata pengguna). Seperti aplikasi lama (vunduh.php/vunduhverifikasi.php):
    satu baris per permintaan x walidata, kolom Nama, Instansi, Jabatan, Tujuan, Walidata, Status, Keterangan,
    Waktu Pengajuan; baris berstatus Pengajuan berlatar hijau.
--}}
@php
    use App\Enums\StatusPermintaan;
    $sel = 'border border-gray-200 px-2.5 py-2';
    $pengajuan = $mode === 'pengajuan';
    $ikonTombol = 'flex size-11 items-center justify-center rounded-md lg:size-8';
    $label = fn (StatusPermintaan $s) => ucfirst($s->value);
@endphp

<x-layouts.admin :judul="$pengajuan ? 'Pengajuan Data' : 'Verifikasi Data'" induk="Unduh Data" :penuh="true">
    @if ($pengajuan)
        <x-slot:aksi>
            <a href="{{ route('admin.unduh.create') }}"
                class="flex min-h-11 items-center gap-1.5 rounded-md bg-primary-500 px-2.5 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 lg:min-h-7">
                <i class="bi bi-plus-lg text-xs" aria-hidden="true"></i> Pengajuan data
            </a>
        </x-slot:aksi>
    @endif

    @if ($baris->isEmpty())
        <div class="flex flex-col items-center px-4 py-24 text-center">
            <i class="bi bi-download text-2xl text-gray-400" aria-hidden="true"></i>
            <p class="mt-3 font-medium">{{ $pengajuan ? 'Belum ada pengajuan data' : 'Belum ada permintaan data untuk pos Anda' }}</p>
            <p class="mt-1 text-gray-600">{{ $pengajuan ? 'Pilih Pengajuan data untuk meminta arsip data pos.' : 'Permintaan baru akan muncul di sini untuk diverifikasi.' }}</p>
        </div>
    @else
        <div class="relative overflow-x-auto px-4 py-4 lg:px-6">
            <p class="mb-3 text-gray-600">Baris hijau = masih menunggu verifikasi.</p>
            <table class="w-full border-collapse text-left">
                <caption class="sr-only">{{ $pengajuan ? 'Pengajuan data' : 'Permintaan data untuk diverifikasi' }}, terbaru di atas</caption>
                <thead class="bg-gray-50 align-bottom text-gray-700">
                    <tr>
                        <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                        <th scope="col" class="{{ $sel }} font-medium">Nama</th>
                        <th scope="col" class="{{ $sel }} font-medium">Instansi</th>
                        <th scope="col" class="{{ $sel }} font-medium">Jabatan</th>
                        <th scope="col" class="{{ $sel }} font-medium">Tujuan</th>
                        <th scope="col" class="{{ $sel }} font-medium">Walidata</th>
                        <th scope="col" class="{{ $sel }} font-medium">Status</th>
                        <th scope="col" class="{{ $sel }} font-medium">Keterangan</th>
                        <th scope="col" class="{{ $sel }} font-medium">Waktu Pengajuan</th>
                        <th scope="col" class="{{ $sel }} w-px"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $b)
                        @php($p = $b['permintaan'])
                        @php($menunggu = $b['status'] === StatusPermintaan::Pengajuan)
                        <tr class="{{ $menunggu ? 'bg-green-50' : 'hover:bg-gray-50' }}">
                            <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $baris->firstItem() + $loop->index }}.</td>
                            <td class="{{ $sel }} min-w-32">{{ $p->nama_pemohon }}</td>
                            <td class="{{ $sel }} min-w-32">{{ $p->instansi }}</td>
                            <td class="{{ $sel }}">{{ $p->jabatan }}</td>
                            <td class="{{ $sel }} min-w-32">{{ $p->tujuan }}</td>
                            <td class="{{ $sel }} min-w-32">{{ $b['walidata'] }} <span class="block text-xs text-gray-600">{{ $b['jumlahPos'] }} pos</span></td>
                            <td class="{{ $sel }} whitespace-nowrap">{{ $label($b['status']) }}</td>
                            <td class="{{ $sel }}">{{ $b['keterangan'] }}</td>
                            <td class="{{ $sel }} whitespace-nowrap tabular-nums">{{ $p->created_at?->translatedFormat('j F Y / H:i') }}</td>
                            <td class="{{ $sel }} px-1.5 py-1">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if ($pengajuan)
                                        {{-- Detail hanya untuk yang disetujui, seperti aplikasi lama. --}}
                                        @if ($b['status'] === StatusPermintaan::Disetujui)
                                            <a href="{{ route('admin.unduh.detail', [$p, $b['walidata_id']]) }}" title="Unduh data" aria-label="Unduh data {{ $b['walidata'] }} untuk {{ $p->nama_pemohon }}"
                                                class="{{ $ikonTombol }} text-primary-600 hover:bg-primary-50 active:bg-primary-100">
                                                <i class="bi bi-download" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.unduh.destroy', [$p, $b['walidata_id']]) }}" data-konfirmasi="Pengajuan data ke {{ $b['walidata'] }} atas nama {{ $p->nama_pemohon }} akan dihapus.">
                                            @csrf @method('DELETE')
                                            <button title="Hapus" aria-label="Hapus pengajuan ke {{ $b['walidata'] }}" class="{{ $ikonTombol }} text-red-700 hover:bg-red-50 active:bg-red-100">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @else
                                        {{-- Setujui / Tutup untuk semua pos walidata ini (aplikasi lama: goverif/1 dan goverif/0). --}}
                                        @foreach ([['disetujui', 'Setujui', 'bi-check-lg', 'text-green-700 hover:bg-green-100'], ['ditutup', 'Tutup', 'bi-x-lg', 'text-red-700 hover:bg-red-50']] as [$status, $teks, $ikon, $warna])
                                            @unless ($b['status']->value === $status)
                                                <form method="POST" action="{{ route('admin.unduh.proses', [$p, $b['walidata_id']]) }}">
                                                    @csrf
                                                    <input type="hidden" name="status" value="{{ $status }}">
                                                    <button title="{{ $teks }}" aria-label="{{ $teks }} permintaan {{ $p->nama_pemohon }}" class="{{ $ikonTombol }} {{ $warna }}">
                                                        <i class="bi {{ $ikon }}" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        @endforeach
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.pagination :data="$baris" />
    @endif
</x-layouts.admin>

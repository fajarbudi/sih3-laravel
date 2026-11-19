{{--
    Detail permintaan yang disetujui (aplikasi lama: vunduhdetail.php): tabel pos x tahun, setiap sel berisi
    tautan file arsip tahun itu. Tautan per pos dan per arsip (dulu semua tahun mengunduh file yang sama).
--}}
@php
    $sel = 'border border-gray-200 px-2.5 py-2';
@endphp

<x-layouts.admin judul="Unduh data" induk="Pengajuan Data" :induk-url="route('admin.unduh.pengajuan')" :penuh="true">
    <div class="px-4 py-4 lg:px-6">
        <div class="mb-4">
            <h2 class="text-base font-semibold">{{ $permintaan->nama_pemohon }}{{ $permintaan->instansi ? ' · '.$permintaan->instansi : '' }}</h2>
            <p class="text-gray-600">Diajukan {{ $permintaan->created_at?->translatedFormat('j F Y, H.i') }}{{ $permintaan->tujuan ? ' untuk '.$permintaan->tujuan : '' }}</p>
        </div>

        @if (! $disetujui)
            {{-- Seperti aplikasi lama: hanya permintaan yang disetujui yang bisa diunduh. --}}
            <div role="status" class="rounded-lg bg-gray-100 px-4 py-3.5">
                <p class="font-medium">Permintaan ini belum disetujui atau sudah ditutup</p>
                <p class="mt-0.5 text-gray-600">File arsip bisa diunduh setelah walidata pemilik pos menyetujuinya.</p>
            </div>
        @elseif ($tahun->isEmpty())
            <div role="status" class="rounded-lg bg-gray-100 px-4 py-3.5">
                <p class="font-medium">Belum ada file arsip untuk pos yang diminta</p>
                <p class="mt-0.5 text-gray-600">Walidata belum mengunggah arsip data pos-pos ini.</p>
            </div>
        @else
            <div class="relative overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <caption class="sr-only">File arsip per pos dan tahun</caption>
                    <thead class="bg-gray-50 text-gray-700">
                        <tr>
                            <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
                            <th scope="col" class="{{ $sel }} font-medium">Pos</th>
                            @foreach ($tahun as $t)
                                <th scope="col" class="{{ $sel }} text-center font-medium tabular-nums">{{ $t }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($item as $i)
                            <tr class="hover:bg-gray-50">
                                <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $loop->iteration }}.</td>
                                <td class="{{ $sel }} min-w-40">{{ $i->pos->nama }}</td>
                                @foreach ($tahun as $t)
                                    @php($a = $arsip->get($i->pos_id)?->get($t))
                                    <td class="{{ $sel }} px-1.5 py-1 text-center">
                                        @if ($a)
                                            <a href="{{ route('admin.unduh.file', [$i->token_unduh, $a]) }}" title="Unduh arsip {{ $t }}" aria-label="Unduh arsip {{ $i->pos->nama }} tahun {{ $t }}"
                                                class="inline-flex size-11 items-center justify-center rounded-md text-primary-600 hover:bg-primary-50 active:bg-primary-100 lg:size-8">
                                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.admin>

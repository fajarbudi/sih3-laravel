{{--
    Kelola arsip satu pos untuk satu tahun (aplikasi lama: modal di vfpos.php): lihat file, unggah/ganti PDF,
    Generate PDF (pos ARR/AWLR), dan hapus. Satu arsip per pos per tahun; unggah/generate mengganti file lama.
--}}
@php
    $tombol = 'flex min-h-11 items-center justify-center gap-1.5 rounded-md px-3.5 font-medium lg:min-h-9';
@endphp

<x-layouts.admin :judul="$pos->nama.' · '.$tahun" induk="File Arsip" :induk-url="route('admin.arsip')" :penuh="true">
    <div class="flex-1 bg-gray-50 px-4 py-6 lg:px-8 lg:py-8">
        <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="px-5 py-6 sm:px-8">
                <h2 class="text-xl font-semibold">Arsip {{ $pos->nama }} tahun {{ $tahun }}</h2>
                <p class="mt-0.5 text-gray-600">{{ $pos->tipe?->label() }} - {{ $pos->jenis?->label() }}</p>
            </div>

            {{-- STATUS FILE --}}
            <div class="border-t border-gray-200 px-5 py-6 sm:px-8">
                @if ($arsip)
                    <div class="flex flex-wrap items-center gap-4">
                        <i class="bi bi-file-earmark-pdf text-3xl text-primary-600" aria-hidden="true"></i>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ basename($arsip->path) }}</p>
                            <p class="text-gray-600 tabular-nums">
                                {{ $arsip->ukuran ? number_format($arsip->ukuran / 1024, 0, ',', '.').' KB · ' : '' }}diperbarui {{ $arsip->updated_at?->translatedFormat('j F Y, H.i') }}
                            </p>
                        </div>
                        <a href="{{ route('admin.arsip.berkas', $arsip) }}" target="_blank" class="{{ $tombol }} border border-gray-450 bg-white text-gray-800 hover:bg-gray-50">
                            <i class="bi bi-box-arrow-up-right text-xs" aria-hidden="true"></i> Buka file
                        </a>
                    </div>
                @else
                    <div role="status" class="rounded-lg bg-gray-100 px-4 py-3.5">
                        <p class="font-medium">Belum ada arsip untuk tahun {{ $tahun }}</p>
                        <p class="mt-0.5 text-gray-600">Unggah file PDF{{ $bisaGenerate ? ', atau buat otomatis dari data pengamatan' : '' }}.</p>
                    </div>
                @endif
            </div>

            {{-- UNGGAH --}}
            <form method="POST" action="{{ route('admin.arsip.unggah', [$pos, $tahun]) }}" enctype="multipart/form-data" class="border-t border-gray-200 px-5 py-6 sm:px-8">
                @csrf
                <label for="file" class="mb-1.5 block font-medium">{{ $arsip ? 'Ganti dengan file lain' : 'Unggah file' }}</label>
                <p id="file-bantuan" class="mb-2 text-gray-600">PDF, maksimal 20 MB.</p>
                <div class="flex flex-wrap items-center gap-3">
                    <input id="file" name="file" type="file" accept="application/pdf" required aria-describedby="file-bantuan @error('file') file-error @enderror"
                        class="block min-h-11 flex-1 rounded-md border border-gray-450 bg-white px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:font-medium">
                    <button class="{{ $tombol }} bg-primary-500 text-white shadow-xs hover:bg-primary-600 active:bg-primary-700">
                        <i class="bi bi-upload" aria-hidden="true"></i> Unggah
                    </button>
                </div>
                @error('file') <p id="file-error" class="mt-1.5 text-xs text-red-700">{{ $message }}</p> @enderror
            </form>

            {{-- GENERATE & HAPUS --}}
            @if ($bisaGenerate || $arsip)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-b-xl border-t border-gray-200 px-5 py-4 sm:px-8">
                    @if ($arsip)
                        <form method="POST" action="{{ route('admin.arsip.destroy', $arsip) }}" data-konfirmasi="File arsip {{ $pos->nama }} tahun {{ $tahun }} akan dihapus.">
                            @csrf @method('DELETE')
                            <button class="{{ $tombol }} -ml-3 text-red-700 hover:bg-red-50 active:bg-red-100">
                                <i class="bi bi-trash3" aria-hidden="true"></i> Hapus file
                            </button>
                        </form>
                    @endif
                    @if ($bisaGenerate)
                        <form method="POST" action="{{ route('admin.arsip.generate', [$pos, $tahun]) }}" class="ml-auto">
                            @csrf
                            <button class="{{ $tombol }} border border-gray-450 bg-white text-gray-800 hover:bg-gray-50"
                                title="Buat PDF data {{ $pos->jenis === App\Enums\JenisPos::Arr ? 'hujan' : 'tinggi muka air' }} harian {{ $tahun }} dari pengamatan manual">
                                <i class="bi bi-gear" aria-hidden="true"></i> {{ $arsip ? 'Generate ulang PDF' : 'Generate PDF' }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>

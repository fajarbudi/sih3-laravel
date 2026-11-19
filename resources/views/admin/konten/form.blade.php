{{--
    Form konten generik (aplikasi lama: modal tambah/ubah di setiap modul publikasi). Isian dari
    KontenController::JENIS[jenis]['isian'], lampiran per slot (unggah baru = ganti; centang = hapus).
    Tata letak kartu sama dengan form modul lain (lihat admin/duga-air/form).
--}}
@php
    $baru = ! $model->exists;
    $kolomPertama = array_key_first($konten['isian']);
    $kontrol = fn ($kolom) => 'block min-h-11 w-full rounded-md border bg-white px-3 text-sm placeholder:text-gray-500 focus:border-primary-500 focus:outline-2 focus:-outline-offset-1 focus:outline-primary-500 lg:min-h-10 '
        .($errors->has($kolom) ? 'border-red-700' : 'border-gray-450 hover:border-gray-600');
    $gagal = fn ($kolom) => $errors->has($kolom) ? 'aria-invalid=true aria-describedby='.str_replace('.', '-', $kolom).'-error' : '';
    $label = 'mb-1.5 block font-medium';
    $bagian = 'border-t border-gray-200 px-5 py-6 sm:px-8';
    $kolomBagian = 'lg:grid lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-10';
    $bantuan = 'mt-0.5 text-gray-600';
    $pesan = 'mt-1.5 text-xs text-red-700';
    $nilai = function ($kolom, $tipe) use ($model) {
        $v = old($kolom, $model->$kolom);
        return match (true) {
            $v instanceof \Carbon\CarbonInterface => $v->toDateString(),
            $v instanceof \BackedEnum => $v->value,
            default => $v,
        };
    };
    $ikon = ['gambar' => 'bi-image', 'pdf' => 'bi-file-earmark-pdf', 'dokumen' => 'bi-file-earmark-text'];
    $terima = ['gambar' => 'image/*', 'pdf' => 'application/pdf', 'dokumen' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip'];
@endphp

<x-layouts.admin :judul="$baru ? 'Data baru' : 'Ubah data'" :induk="$konten['judul']" :induk-url="route('admin.'.$jenis)" :penuh="true">
    <div class="flex-1 bg-gray-50 px-4 py-6 lg:px-8 lg:py-8">
        <form id="form-konten" method="POST" enctype="multipart/form-data"
            action="{{ $baru ? route('admin.'.$jenis.'.store') : route('admin.'.$jenis.'.update', $model->id) }}"
            class="mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white shadow-xs">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            <div class="px-5 py-6 sm:px-8">
                <h2 class="text-xl font-semibold">{{ $baru ? 'Tambah' : 'Ubah' }} {{ $konten['judul'] }}</h2>
                <p class="{{ $bantuan }}">{{ $konten['isian'][$kolomPertama][0] }} wajib diisi.</p>
                @if ($errors->any())
                    <div role="alert" class="mt-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <i class="bi bi-exclamation-circle-fill mt-px" aria-hidden="true"></i>
                        <p>Data belum tersimpan. Perbaiki {{ $errors->count() === 1 ? 'isian' : $errors->count().' isian' }} yang ditandai.</p>
                    </div>
                @endif
            </div>

            {{-- ISI --}}
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-isi">
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-isi" class="font-semibold">Isi</h3>
                        <p class="{{ $bantuan }}">Data yang tampil di portal.</p>
                    </div>
                    <div class="grid gap-5 pt-5 sm:grid-cols-2 lg:pt-0">
                        @foreach ($konten['isian'] as $kolom => $isian)
                            @php([$teks, $tipe] = $isian)
                            <div @class(['sm:col-span-2' => in_array($tipe, ['area', 'url'], true) || $kolom === $kolomPertama])>
                                @if ($tipe === 'tampil')
                                    <span class="{{ $label }}">{{ $teks }}</span>
                                    <label class="flex min-h-11 cursor-pointer items-center gap-2.5 lg:min-h-10">
                                        <input type="hidden" name="{{ $kolom }}" value="0">
                                        <input id="{{ $kolom }}" type="checkbox" name="{{ $kolom }}" value="1" role="switch" class="peer sr-only" @checked(old($kolom, $model->$kolom))>
                                        <span class="relative h-6 w-11 shrink-0 rounded-full bg-gray-450 transition-colors peer-checked:bg-primary-500 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-500 motion-reduce:transition-none
                                            after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-xs after:transition-transform peer-checked:after:translate-x-5 motion-reduce:after:transition-none" aria-hidden="true"></span>
                                        <span class="hidden peer-checked:inline" aria-hidden="true">{{ $isian[2][0] }}</span>
                                        <span class="text-gray-600 peer-checked:hidden" aria-hidden="true">{{ $isian[2][1] }}</span>
                                    </label>
                                @else
                                    <label for="{{ $kolom }}" class="{{ $label }}">{{ $teks }}</label>
                                    @switch($tipe)
                                        @case('area')
                                            <textarea id="{{ $kolom }}" name="{{ $kolom }}" rows="4" class="{{ $kontrol($kolom) }} resize-y py-2.5 leading-relaxed" {!! $gagal($kolom) !!}>{{ $nilai($kolom, $tipe) }}</textarea>
                                            @break
                                        @case('pilihan')
                                        @case('kategori')
                                        @case('pos')
                                        @case('walidata')
                                            @php($pilihan = match ($tipe) { 'pilihan' => App\Http\Controllers\Admin\KontenController::pilihan($isian[2]), 'kategori' => $kategori[$kolom]->all(), 'pos' => $daftarPos->all(), 'walidata' => $daftarWalidata->all() })
                                            <select id="{{ $kolom }}" name="{{ $kolom }}" class="{{ $kontrol($kolom) }}" {!! $gagal($kolom) !!}>
                                                <option value="">Pilih {{ strtolower($teks) }}</option>
                                                @foreach ($pilihan as $id => $nama)
                                                    <option value="{{ $id }}" @selected((string) $nilai($kolom, $tipe) === (string) $id)>{{ $nama }}</option>
                                                @endforeach
                                            </select>
                                            @break
                                        @default
                                            <input id="{{ $kolom }}" name="{{ $kolom }}" value="{{ $nilai($kolom, $tipe) }}"
                                                type="{{ ['tanggal' => 'date', 'angka' => 'number', 'url' => 'url'][$tipe] ?? 'text' }}"
                                                @if ($tipe === 'angka') step="any" inputmode="decimal" @endif
                                                @if ($kolom === $kolomPertama) required @endif
                                                class="{{ $kontrol($kolom) }} {{ in_array($tipe, ['tanggal', 'angka'], true) ? 'tabular-nums' : '' }}" {!! $gagal($kolom) !!}>
                                    @endswitch
                                @endif
                                @error($kolom) <p id="{{ $kolom }}-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </fieldset>

            {{-- LAMPIRAN --}}
            @if ($konten['lampiran'])
            <fieldset class="{{ $bagian }}" aria-labelledby="bagian-lampiran">
                <div class="{{ $kolomBagian }}">
                    <div>
                        <h3 id="bagian-lampiran" class="font-semibold">Lampiran</h3>
                        <p class="{{ $bantuan }}">Opsional, maksimal 20 MB per file. Unggah file baru untuk mengganti yang lama.</p>
                    </div>
                    <div class="divide-y divide-gray-200 pt-5 lg:pt-0">
                        @foreach ($konten['lampiran'] as [$teks, $jenisFile, $urutan])
                            @php($slot = $jenisFile.'-'.$urutan)
                            @php($ada = $lampiranAda->get($slot))
                            <div class="grid items-center gap-3 py-3 first:pt-0 sm:grid-cols-[8rem_minmax(0,1fr)]">
                                <label for="file-{{ $slot }}" class="font-medium">{{ $teks }}</label>
                                <div class="space-y-2">
                                    @if ($ada)
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                            <a href="{{ route('admin.konten.lampiran', $ada) }}" target="_blank" class="flex min-w-0 items-center gap-1.5 text-primary-600 underline-offset-2 hover:underline">
                                                <i class="bi {{ $ikon[$jenisFile] }}" aria-hidden="true"></i><span class="truncate">{{ basename($ada->path) }}</span>
                                            </a>
                                            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-red-700 lg:min-h-8">
                                                <input type="checkbox" name="hapus[{{ $slot }}]" value="1" class="size-4 accent-red-700"> Hapus
                                            </label>
                                        </div>
                                    @endif
                                    <input id="file-{{ $slot }}" name="file[{{ $slot }}]" type="file" accept="{{ $terima[$jenisFile] }}"
                                        @error('file.'.$slot) aria-invalid="true" aria-describedby="file-{{ $slot }}-error" @enderror
                                        class="block min-h-11 w-full rounded-md border border-gray-450 bg-white px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1 file:font-medium">
                                    @error('file.'.$slot) <p id="file-{{ $slot }}-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </fieldset>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-3 rounded-b-xl border-t border-gray-200 px-5 py-4 sm:px-8">
                <a href="{{ route('admin.'.$jenis) }}" class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md border border-gray-450 bg-white px-3.5 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 sm:flex-none lg:min-h-9">
                    <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Batal
                </a>
                <button data-simpan class="flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-md bg-primary-500 px-4 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 sm:flex-none lg:min-h-9">
                    <i class="bi bi-check2" aria-hidden="true"></i> <span>{{ $baru ? 'Simpan data' : 'Simpan perubahan' }}</span>
                </button>
            </div>
        </form>
    </div>

    @push('skrip')
        <script>
            // Keadaan menyimpan: tombol dikunci agar data (dan unggahan) tidak terkirim dua kali.
            document.getElementById('form-konten').addEventListener('submit', () => {
                const tombol = document.querySelector('[data-simpan]');
                tombol.disabled = true;
                tombol.querySelector('i').className = 'bi bi-arrow-repeat motion-safe:animate-spin';
                tombol.querySelector('span').textContent = 'Menyimpan…';
            });
        </script>
    @endpush
</x-layouts.admin>

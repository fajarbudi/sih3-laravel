{{--
    Input data harian setahun dengan tempel dari Excel (aplikasi lama: vinput.php). Langkah: pilih pos + tahun, tempel,
    Pratinjau (grid tanggal x bulan), lalu Simpan. Data tempelan dikirim ulang saat simpan (tanpa file sementara).
--}}
@php
    $kontrol = fn ($kolom) => 'block min-h-11 w-full rounded-md border bg-white px-3 text-sm focus:border-primary-500 focus:outline-2 focus:-outline-offset-1 focus:outline-primary-500 lg:min-h-10 '
        .($errors->has($kolom) ? 'border-red-700' : 'border-gray-450 hover:border-gray-600');
    $gagal = fn ($kolom) => $errors->has($kolom) ? "aria-invalid=true aria-describedby=$kolom-error" : '';
    $label = 'mb-1.5 block font-medium';
    $pesan = 'mt-1.5 text-xs text-red-700';
    $sel = 'border border-gray-200 px-2 py-1.5';
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $hasil ??= null;
    $angka = fn ($n) => str_replace('.', ',', rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.'));
@endphp

<x-layouts.admin :judul="$input['judul']" induk="Input" :penuh="true">
    <div class="flex-1 space-y-6 bg-gray-50 px-4 py-6 lg:px-8 lg:py-8">
        <form method="POST" action="{{ route('admin.input.'.$jenis.'.pratinjau') }}" class="mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white shadow-xs">
            @csrf
            <div class="px-5 py-6 sm:px-8">
                <h2 class="text-xl font-semibold">{{ $input['judul'] }}</h2>
                <p class="mt-0.5 text-gray-600">Tempel data harian setahun dari Excel. Setiap nilai disimpan sebagai pengamatan jam 07.00; data yang sudah ada diperbarui.</p>
                @if ($errors->any())
                    <div role="alert" class="mt-4 flex gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <i class="bi bi-exclamation-circle-fill mt-px" aria-hidden="true"></i>
                        <p>Perbaiki {{ $errors->count() === 1 ? 'isian' : $errors->count().' isian' }} yang ditandai.</p>
                    </div>
                @endif
            </div>
            <div class="grid gap-5 border-t border-gray-200 px-5 py-6 sm:grid-cols-4 sm:px-8">
                <div class="sm:col-span-3">
                    <label for="pos_id" class="{{ $label }}">POS</label>
                    <select id="pos_id" name="pos_id" required class="{{ $kontrol('pos_id') }}" {!! $gagal('pos_id') !!}>
                        <option value="">Pilih pos</option>
                        @foreach ($daftarPos as $id => $nama)
                            <option value="{{ $id }}" @selected((string) old('pos_id', $pos_id ?? '') === (string) $id)>{{ $nama }}</option>
                        @endforeach
                    </select>
                    @error('pos_id') <p id="pos_id-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tahun" class="{{ $label }}">Tahun</label>
                    <select id="tahun" name="tahun" required class="{{ $kontrol('tahun') }}" {!! $gagal('tahun') !!}>
                        @foreach (range(now()->year, 2010) as $t)
                            <option value="{{ $t }}" @selected((int) old('tahun', $tahun ?? now()->year) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-4">
                    <label for="tempel" class="{{ $label }}">Data dari Excel</label>
                    <p id="tempel-bantuan" class="mb-2 text-gray-600">Salin blok di Excel: kolom pertama tanggal (1–31), lalu 12 kolom Jan–Des ({{ $input['satuan'] }}). Baris judul dan ringkasan diabaikan; sel kosong, "-", dan "x" dilewati.</p>
                    <textarea id="tempel" name="tempel" rows="10" required aria-describedby="tempel-bantuan @error('tempel') tempel-error @enderror"
                        placeholder="1&#9;12,5&#9;0&#9;…&#10;2&#9;3&#9;8,2&#9;…" class="{{ $kontrol('tempel') }} resize-y py-2.5 font-mono text-xs leading-relaxed">{{ old('tempel', $tempel ?? '') }}</textarea>
                    @error('tempel') <p id="tempel-error" class="{{ $pesan }}">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end rounded-b-xl border-t border-gray-200 px-5 py-4 sm:px-8">
                <button class="flex min-h-11 items-center justify-center gap-1.5 rounded-md border border-gray-450 bg-white px-4 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 lg:min-h-9">
                    <i class="bi bi-eye" aria-hidden="true"></i> Pratinjau
                </button>
            </div>
        </form>

        @if ($hasil)
            <section aria-labelledby="judul-pratinjau" class="mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-5 sm:px-8">
                    <div>
                        <h2 id="judul-pratinjau" class="text-base font-semibold">Pratinjau: {{ $daftarPos[$pos_id] ?? '' }}, tahun {{ $tahun }}</h2>
                        <p class="text-gray-600">{{ count($hasil['nilai']) }} nilai akan disimpan.</p>
                    </div>
                    @if ($hasil['nilai'])
                        <form method="POST" action="{{ route('admin.input.'.$jenis.'.store') }}">
                            @csrf
                            <input type="hidden" name="pos_id" value="{{ $pos_id }}">
                            <input type="hidden" name="tahun" value="{{ $tahun }}">
                            <textarea name="tempel" hidden>{{ $tempel }}</textarea> {{-- textarea: baris baru dan tab terkirim utuh --}}
                            <button class="flex min-h-11 items-center gap-1.5 rounded-md bg-primary-500 px-4 font-medium text-white shadow-xs hover:bg-primary-600 active:bg-primary-700 lg:min-h-9">
                                <i class="bi bi-check2" aria-hidden="true"></i> Simpan {{ count($hasil['nilai']) }} data
                            </button>
                        </form>
                    @endif
                </div>
                @if ($hasil['ditolak'])
                    <p role="alert" class="mx-5 mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 sm:mx-8">
                        Ditolak karena bukan angka positif atau terlalu besar (tidak disimpan): {{ implode(', ', $hasil['ditolak']) }} (tanggal/bulan).
                    </p>
                @endif
                @if ($hasil['dilewati'])
                    <p role="status" class="mx-5 mb-4 rounded-lg bg-amber-50 px-4 py-3 text-amber-900 sm:mx-8">
                        Dilewati karena tanggal tidak ada di tahun {{ $tahun }}: {{ implode(', ', $hasil['dilewati']) }} (tanggal/bulan).
                    </p>
                @endif
                @if ($hasil['nilai'])
                    <div class="relative overflow-x-auto border-t border-gray-200 px-5 py-4 sm:px-8">
                        <table class="w-full border-collapse text-left">
                            <caption class="sr-only">Pratinjau nilai per tanggal dan bulan ({{ $input['satuan'] }})</caption>
                            <thead class="bg-gray-50 text-gray-700">
                                <tr>
                                    <th scope="col" class="{{ $sel }} w-px text-center font-medium">Tgl</th>
                                    @foreach ($bulan as $b)
                                        <th scope="col" class="{{ $sel }} text-right font-medium">{{ $b }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (range(1, 31) as $t)
                                    <tr>
                                        <th scope="row" class="{{ $sel }} text-center font-normal tabular-nums text-gray-600">{{ $t }}</th>
                                        @foreach ($bulan as $nomor => $b)
                                            <td class="{{ $sel }} text-right tabular-nums {{ checkdate($nomor, $t, (int) $tahun) ? '' : 'bg-gray-100' }}">
                                                {{ isset($hasil['grid'][$t][$nomor]) ? $angka($hasil['grid'][$t][$nomor]) : '' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="border-t border-gray-200 px-5 py-6 text-gray-600 sm:px-8">Tidak ada angka yang terbaca. Pastikan kolom pertama berisi tanggal dan kolom dipisah tab (salin langsung dari Excel).</p>
                @endif
            </section>
        @endif
    </div>
</x-layouts.admin>

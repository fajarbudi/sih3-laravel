{{--
    Tabel rekap kualitas air (ditranspos seperti aplikasi lama, Rkualitas_air.php):
    kolom No., Keterangan, lalu sampel bernomor 1, 2, 3, ... (terakhir diinput di kiri); baris dari RekapKualitasAirController::baris().
    Angka apa adanya (sampai 4 desimal); Excel memakai titik desimal.
--}}
@php
    $excel ??= false;
    $sel ??= '';
    $tampil = fn ($v) => is_float($v)
        ? str_replace('.', $excel ? '.' : ',', rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.'))
        : $v;
@endphp
<table class="w-full border-collapse text-left">
    <caption class="sr-only">{{ $judul }}, satu kolom per sampel</caption>
    <thead class="bg-gray-50 align-bottom text-gray-700">
        <tr>
            <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
            <th scope="col" class="{{ $sel }} font-medium">Keterangan</th>
            @foreach ($sampel as $s)
                <th scope="col" class="{{ $sel }} min-w-28 text-right font-medium tabular-nums">{{ $loop->iteration }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($baris as $label => $ambil)
            <tr class="hover:bg-gray-50">
                <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $loop->iteration }}.</td>
                <th scope="row" class="{{ $sel }} min-w-48 font-normal">{{ $label }}</th>
                @foreach ($sampel as $s)
                    @php($v = $ambil($s))
                    <td class="{{ $sel }} {{ is_float($v) ? 'text-right tabular-nums' : '' }}">{{ $tampil($v) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

{{--
    Tabel rekap pivot (curah hujan per tanggal, muka air tanah per pos), dipakai di layar, cetak, dan Excel.
    Kolom sama dengan aplikasi lama (Rarr.php/Rmat.php): No., POS, [Tanggal], Jan s.d. Des (satuan).
    Sel = rata-rata dengan 2 desimal seperti aplikasi lama; Excel memakai titik desimal.
--}}
@php
    $excel ??= false;
    $sel ??= '';
    $angka = fn ($n) => $n === null ? '' : number_format($n, 2, $excel ? '.' : ',', '');
@endphp
<table class="w-full border-collapse text-left">
    <caption class="sr-only">{{ $judul }}</caption>
    <thead class="bg-gray-50 align-bottom text-gray-700">
        <tr>
            <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
            @if ($tampilPos)
                <th scope="col" class="{{ $sel }} font-medium">POS</th>
            @endif
            @if ($rekap['perHari'])
                <th scope="col" class="{{ $sel }} w-px text-center font-medium">Tanggal</th>
            @endif
            @foreach (App\Http\Controllers\Admin\RekapPivotController::BULAN as $bulan)
                <th scope="col" class="{{ $sel }} min-w-16 text-right font-medium">{{ $bulan }} ({{ $rekap['satuan'] }})</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($baris as $b)
            <tr class="hover:bg-gray-50">
                <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $loop->iteration }}.</td>
                @if ($tampilPos)
                    <td class="{{ $sel }} min-w-32">{{ $b['pos'] }}</td>
                @endif
                @if ($rekap['perHari'])
                    <td class="{{ $sel }} text-center tabular-nums">{{ $b['tanggal'] }}</td>
                @endif
                @foreach (App\Http\Controllers\Admin\RekapPivotController::BULAN as $nomor => $bulan)
                    <td class="{{ $sel }} text-right tabular-nums">{{ $angka($b['bulan'][$nomor] ?? null) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

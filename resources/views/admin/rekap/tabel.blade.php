{{--
    Tabel rekap, dipakai di layar, halaman cetak, dan unduhan Excel.
    Kolom sama dengan aplikasi lama: No., POS, Waktu, lalu kolom nilai dari RekapController::JENIS. Angka apa adanya.
    $excel: angka ditulis dengan titik desimal agar dibaca Excel sebagai angka.
--}}
@php
    $excel ??= false;
    $angka = fn ($n) => $n === null ? '' : ($excel ? rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.')
        : str_replace('.', ',', rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.')));
    $sel ??= '';
@endphp
<table class="w-full border-collapse text-left">
    <caption class="sr-only">{{ $judul }}, terakhir diinput di atas</caption>
    <thead class="bg-gray-50 align-bottom text-gray-700">
        <tr>
            <th scope="col" class="{{ $sel }} w-px text-center font-medium">No.</th>
            @if ($tampilPos)
                <th scope="col" class="{{ $sel }} font-medium">POS</th>
            @endif
            <th scope="col" class="{{ $sel }} font-medium">Waktu</th>
            @foreach ($rekap['nilai'] as $teks)
                <th scope="col" class="{{ $sel }} min-w-24 text-right font-medium">{{ $teks }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $b)
            <tr class="hover:bg-gray-50">
                <td class="{{ $sel }} text-center tabular-nums text-gray-600">{{ $loop->iteration }}.</td>
                @if ($tampilPos)
                    <td class="{{ $sel }} min-w-32">{{ $b->pos->nama }}</td>
                @endif
                <td class="{{ $sel }} whitespace-nowrap tabular-nums">{{ $b->waktu->translatedFormat('j F Y') }} / {{ $b->waktu->format('H:i') }}</td>
                @foreach ($rekap['nilai'] as $kolom => $teks)
                    <td class="{{ $sel }} text-right tabular-nums">{{ $angka($b->$kolom) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

{{--
    PDF buku data harian setahun (aplikasi lama: views/dapur/cetak/carr.php dan cawlr.php), dirender dompdf.
    Kepala: identitas pos. Tabel: tanggal 1-31 x bulan Jan-Des; sel abu-abu = tanggal tidak ada di bulan itu.
    Angka 1 desimal; "-" = nol (tidak ada hujan); "x" = tidak ada data.
    ARR: + Hujan Maks, Jml. Curah Hujan, Jml. Hari Hujan (hujan > 0), jumlah per tanggal 1-15 dan 16-31, ringkasan tahunan.
--}}
@php
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $bulanPanjang = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $angka = fn ($n) => ! $n ? '-' : number_format($n, 1, '.', ',');

    // Ringkasan per bulan dan tahunan.
    $ringkas = [];
    $maksTahun = 0;
    $maksTanggal = '';
    foreach (range(1, 12) as $b) {
        $isi = $nilai[$b] ?? [];
        $paruh1 = array_filter($isi, fn ($t) => $t <= 15, ARRAY_FILTER_USE_KEY);
        $paruh2 = array_filter($isi, fn ($t) => $t > 15, ARRAY_FILTER_USE_KEY);
        $ringkas[$b] = [
            'maks' => $isi ? max($isi) : null,
            'jumlah' => $isi ? array_sum($isi) : null,
            'hariHujan' => count(array_filter($isi, fn ($v) => $v > 0)),
            'data1' => count($paruh1), 'jumlah1' => $paruh1 ? array_sum($paruh1) : null,
            'data2' => count($paruh2), 'jumlah2' => $paruh2 ? array_sum($paruh2) : null,
        ];
        foreach ($isi as $t => $v) {
            if ($v > $maksTahun) {
                $maksTahun = $v;
                $maksTanggal = $t.' '.$bulanPanjang[$b].' '.$tahun;
            }
        }
    }
    $identitas = [
        ['Nama Pos', $pos->nama, 'Provinsi', $pos->provinsi],
        ['Nomor Pos', $pos->id, 'Kabupaten', $pos->kabupaten],
        ['Jenis Alat', $pos->metode?->name, 'Kecamatan', $pos->kecamatan],
        ['Koordinat', $pos->latitude !== null ? $pos->latitude.', '.$pos->longitude : '', 'Desa/Kampung', $pos->desa],
        ['Elevasi', $pos->elevasi, 'Tahun Pendirian', $pos->tahun_pembuatan],
        ['Wilayah Sungai', $pos->wilayah_sungai, 'Pengelola', $pos->walidata?->nama],
        ['Daerah Aliran Sungai', $pos->das, 'Hasil Kendali Mutu', ''],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $hujan ? 'Data Hujan Harian' : 'Data Tinggi Muka Air' }} {{ $pos->nama }} {{ $tahun }}</title>
    <style>
        @page { margin: 28px 20px; }
        body { font-family: Helvetica, sans-serif; /* font standar PDF: tidak disematkan, file kecil (aplikasi lama juga helvetica) */ font-size: 8.5px; color: #111; }
        h1 { font-size: 13px; text-align: center; margin: 0 0 6px; padding-bottom: 4px; border-bottom: 1px solid #222; }
        .identitas { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .identitas td { padding: 1px 2px; vertical-align: top; }
        .data { width: 100%; border-collapse: collapse; }
        .data th, .data td { border: 0.5px solid #222; padding: 1.5px 2px; text-align: center; }
        .data th { font-weight: bold; }
        .data .label { text-align: left; white-space: nowrap; }
        .kosong { background: #7e7e7e; }
        .pemisah td { border-top: 1.2px solid #222; }
        .catatan { margin-top: 6px; }
    </style>
</head>
<body>
    <h1>{{ $hujan ? 'DATA HUJAN HARIAN' : 'DATA TINGGI MUKA AIR' }} TAHUN {{ $tahun }}</h1>

    <table class="identitas">
        @foreach ($identitas as [$l1, $v1, $l2, $v2])
            <tr>
                <td style="width: 16%">{{ $l1 }}</td><td style="width: 34%">: {{ $v1 }}</td>
                <td style="width: 16%">{{ $l2 }}</td><td style="width: 34%">: {{ $v2 }}</td>
            </tr>
        @endforeach
    </table>

    <table class="data">
        <thead>
            <tr>
                <th rowspan="2" style="width: 16%">Tanggal</th>
                <th colspan="12">Bulan</th>
            </tr>
            <tr>
                @foreach ($bulan as $b)
                    <th style="width: 7%">{{ $b }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach (range(1, 31) as $t)
                <tr @class(['pemisah' => $hujan && $t === 16])>
                    <td>{{ $t }}</td>
                    @foreach (range(1, 12) as $b)
                        @if ($t > $hariPerBulan[$b])
                            <td class="kosong"></td>
                        @else
                            <td>{{ array_key_exists($t, $nilai[$b] ?? []) ? $angka($nilai[$b][$t]) : 'x' }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach

            @if ($hujan)
                @foreach ([
                    ['Hujan Maks', fn ($r) => $angka($r['maks'])],
                    ['Jml. Curah Hujan', fn ($r) => $angka($r['jumlah'])],
                    ['Jml. Hari Hujan', fn ($r) => $r['hariHujan'] ?: ''],
                    ['Jml. data (1-15)', fn ($r) => $r['data1'] ?: ''],
                    ['Jml. Hujan (1-15)', fn ($r) => $angka($r['jumlah1'])],
                    ['Jml. data (16-31)', fn ($r) => $r['data2'] ?: ''],
                    ['Jml. Hujan (16-31)', fn ($r) => $angka($r['jumlah2'])],
                ] as $i => [$label, $isi])
                    <tr @class(['pemisah' => $i === 0])>
                        <td class="label">{{ $label }}</td>
                        @foreach (range(1, 12) as $b)
                            <td>{{ $isi($ringkas[$b]) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="pemisah">
                    <td rowspan="2" class="label">Tahunan</td>
                    <td colspan="4">Jumlah Curah Hujan</td>
                    <td colspan="4">Jumlah Hari Hujan</td>
                    <td colspan="4">Hujan Maksimum</td>
                </tr>
                <tr>
                    <td colspan="4">{{ $angka(array_sum(array_column($ringkas, 'jumlah'))) }}</td>
                    <td colspan="4">{{ array_sum(array_column($ringkas, 'hariHujan')) }}</td>
                    <td colspan="4">{{ $angka($maksTahun) }}{{ $maksTanggal ? ' ('.$maksTanggal.')' : '' }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="catatan">
        Catatan: @if ($hujan) - : tidak ada hujan; 0 : hujan kurang dari 0.5 mm; @else - : nol; @endif x : tidak ada data.
        @unless ($hujan) Nilai = rata-rata tinggi muka air limpas harian (m). @endunless
    </p>
</body>
</html>

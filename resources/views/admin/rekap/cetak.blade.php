{{--
    Halaman cetak (dialog cetak terbuka otomatis) dan isi file Excel (.xls = tabel HTML yang dibuka Excel).
    Sengaja tanpa layout admin dan tanpa Vite: gaya sederhana bawaan di bawah.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    @unless ($excel ?? false)
        <style>
            body { font: 12px/1.4 system-ui, sans-serif; color: #111; margin: 24px; }
            h1 { font-size: 16px; margin: 0 0 4px; }
            p { margin: 0 0 12px; color: #444; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #999; padding: 4px 6px; vertical-align: top; }
            th { background: #eee; }
            .text-right { text-align: right; } .text-center { text-align: center; }
            .whitespace-nowrap { white-space: nowrap; } .sr-only { display: none; }
        </style>
    @endunless
</head>
<body>
    <h1>{{ $judul }}</h1>
    <p>Portal SIH3 Wilayah Sungai Serayu Opak · {{ number_format($data->count(), 0, ',', '.') }} {{ $satuanJumlah ?? (isset($baris) ? 'baris' : 'data') }} · dicetak {{ now()->translatedFormat('j F Y, H.i') }}</p>
    @include($tabel ?? 'admin.rekap.tabel', ['sel' => '', 'excel' => $excel ?? false])
    @unless ($excel ?? false)
        <script>window.addEventListener('load', () => window.print());</script>
    @endunless
</body>
</html>

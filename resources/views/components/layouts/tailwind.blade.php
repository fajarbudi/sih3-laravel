{{--
    Tailwind CSS lewat CDN (tanpa build step), sesuai permintaan pemilik proyek.
    Gaya mengikuti Linear atas permintaan pemilik: Inter, ukuran teks dasar 13px, abu-abu netral.
    Warna utama tetap biru logo Portal SIH3.
--}}
<link rel="icon" type="image/png" href="{{ asset('images/sih3-favicon.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
{{-- Ikon: Bootstrap Icons, pakai <i class="bi bi-nama" aria-hidden="true"></i> (daftar nama: icons.getbootstrap.com) --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.11"></script>
<style type="text/tailwindcss">
    @theme {
        --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
        --text-sm: 0.8125rem;           /* 13px, ukuran teks utama ala Linear */
        --text-sm--line-height: 1.25rem;
        --color-primary-50: #e8f1fc;
        --color-primary-100: #cfe2f9;
        --color-primary-500: #0b63ce;
        --color-primary-600: #0a57b5;
        --color-primary-700: #08489a;
    }

    @layer base {
        button, [role="button"], summary, label[for], select { cursor: pointer; }
        button:disabled { cursor: not-allowed; }
        :focus-visible { outline: 2px solid var(--color-primary-500); outline-offset: 2px; }
    }
</style>

{{--
    Aset bersama panel admin, halaman login, dan portal, dibangun Vite (resources/css/app.css):
    Tailwind, Bootstrap Icons, dan font, semuanya disajikan dari server sendiri.
    Admin hanya memuat Inter; portal menambah Plus Jakarta Sans lewat prop `fonts`.
    Tidak ada skrip pihak ketiga di <head> (antislop R-40). Jalankan `npm run build`, atau `npm run dev` saat mengembangkan.
--}}
@props(['fonts' => ['inter']])
<link rel="icon" type="image/png" href="{{ asset('images/sih3-favicon.png') }}">
@fonts($fonts)
@vite('resources/css/app.css')

{{--
    Aset bersama panel admin dan halaman login, dibangun Vite (resources/css/app.css):
    Tailwind, Bootstrap Icons, dan font Inter, semuanya disajikan dari server sendiri.
    Tidak ada skrip pihak ketiga di <head> (antislop R-40). Jalankan `npm run build`, atau `npm run dev` saat mengembangkan.
--}}
<link rel="icon" type="image/png" href="{{ asset('images/sih3-favicon.png') }}">
@fonts
@vite('resources/css/app.css')

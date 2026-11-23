{{--
    Hero halaman dalam portal (seperti sub-banner portal lama): foto bendung yang sama dengan beranda, lebih pendek,
    pil emas kecil + judul + garis emas + keterangan. Dipakai bersama <x-layouts.portal :hero-gelap="true">,
    lalu isi halaman dibuka dengan <x-portal.lembar> yang naik menutupi bawah hero.

    Pakai: <x-portal.hero label="Publikasi" judul="Buletin">Keterangan singkat.</x-portal.hero>
--}}
@props(['label', 'judul'])

<section class="relative isolate -mt-16 overflow-hidden bg-primary-950 pt-16 text-center text-white lg:-mt-[4.5rem] lg:pt-[4.5rem]">
    <img src="{{ asset('images/portal/hero-1920.jpg') }}" srcset="{{ asset('images/portal/hero-960.jpg') }} 960w, {{ asset('images/portal/hero-1920.jpg') }} 1920w"
        sizes="100vw" alt="" width="1920" height="1103" fetchpriority="high" class="absolute inset-0 -z-20 size-full object-cover object-[50%_60%]">
    <div class="absolute inset-0 -z-10 bg-linear-to-b from-primary-950/70 via-primary-950/45 to-primary-950/55" aria-hidden="true"></div>
    <div class="mx-auto max-w-3xl px-4 pb-24 pt-12 [text-shadow:0_2px_18px_rgb(6_26_53/0.55)] sm:pb-28 sm:pt-16">
        <h1>
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-amber-300 ring-1 ring-inset ring-white/20 backdrop-blur-sm sm:text-sm">
                <span class="size-1.5 rounded-full bg-amber-300" aria-hidden="true"></span>{{ $label }}
            </span>
            <span class="mt-4 block text-3xl font-extrabold tracking-tight text-balance sm:text-5xl">{{ $judul }}</span>
        </h1>
        <span class="mx-auto mt-5 block h-1 w-14 rounded-full bg-amber-300" aria-hidden="true"></span>
        <p class="mx-auto mt-5 max-w-2xl text-white/90 sm:text-lg">{{ $slot }}</p>
    </div>
</section>

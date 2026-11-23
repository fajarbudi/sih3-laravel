{{--
    Jendela detail halaman Publikasi (pengganti modal #lihatcontent lama). Gaya sama dengan jendela detail pos.
    Tombol pembuka: <button data-jendela="id-template" data-judul="Judul">, isinya <template id="id-template">...</template>
    di halaman yang sama (gambar/PDF/video di dalam template baru dimuat saat jendela dibuka). Isi dikosongkan saat
    ditutup agar video/PDF berhenti.

    Pakai sekali per halaman: <x-portal.jendela />  (lebar="lebar" untuk PDF/gambar besar)
--}}
@props(['lebar' => 'w-[min(56rem,calc(100%-2rem))]'])

<dialog id="jendela" aria-labelledby="jendela-judul"
    class="m-auto max-h-[calc(100%-2rem)] {{ $lebar }} overflow-hidden rounded-3xl bg-white p-0 text-gray-900 shadow-2xl backdrop:bg-gray-950/60 backdrop:backdrop-blur-sm">
    <div class="flex max-h-[calc(100vh-2rem)] flex-col">
        <div class="flex items-start gap-3 px-5 pb-3 pt-5 sm:px-7 sm:pt-6">
            <h2 id="jendela-judul" class="min-w-0 flex-1 text-lg font-extrabold leading-snug tracking-tight text-gray-950 sm:text-xl"></h2>
            <button type="button" data-tutup aria-label="Tutup" class="-mr-1 flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 hover:text-gray-950">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div id="jendela-isi" class="overflow-y-auto px-5 pb-6 sm:px-7 sm:pb-7"></div>
    </div>
</dialog>

@push('skrip')
    <script>
        (() => {
            const jendela = document.getElementById('jendela');
            const isi = document.getElementById('jendela-isi');
            let pembuka;
            document.addEventListener('click', e => {
                const tombol = e.target.closest('[data-jendela]');
                if (!tombol) return;
                pembuka = tombol;
                document.getElementById('jendela-judul').textContent = tombol.dataset.judul;
                isi.replaceChildren(document.getElementById(tombol.dataset.jendela).content.cloneNode(true));
                isi.scrollTop = 0;
                jendela.showModal();
            });
            jendela.querySelector('[data-tutup]').addEventListener('click', () => jendela.close());
            jendela.addEventListener('click', e => e.target === jendela && jendela.close());
            jendela.addEventListener('close', () => { isi.replaceChildren(); pembuka?.focus(); });

            // Galeri foto di dalam jendela (album): klik miniatur mengganti foto utama.
            isi.addEventListener('click', e => {
                const mini = e.target.closest('[data-foto]');
                if (!mini) return;
                const utama = isi.querySelector('[data-foto-utama]');
                utama.src = mini.dataset.foto;
                isi.querySelectorAll('[data-foto]').forEach(m => m.setAttribute('aria-current', String(m === mini)));
            });
        })();
    </script>
@endpush

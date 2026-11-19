{{--
    Modal konfirmasi hapus, dipasang sekali di layout admin (menggantikan confirm() bawaan browser).
    Pakai: beri form hapus atribut data-konfirmasi="Pesan yang menjelaskan data yang akan dihapus."
           <form method="POST" action="..." data-konfirmasi="Data ... akan dihapus.">@csrf @method('DELETE') ...</form>
    Saat form dikirim, modal muncul; form baru benar-benar dikirim setelah "Ya, hapus".

    <dialog> bawaan: fokus terkunci di dalam modal, Escape menutup, dikenali pembaca layar sebagai dialog.
    Fokus awal di Batal agar Enter yang tidak sengaja tidak menghapus data.
--}}
<dialog id="konfirmasi-hapus" aria-labelledby="konfirmasi-hapus-judul" aria-describedby="konfirmasi-hapus-pesan"
    class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl border border-gray-200 bg-white p-0 text-sm text-gray-900 shadow-xl shadow-gray-950/10 backdrop:bg-gray-950/40">
    <div class="flex gap-3.5 px-5 pb-4 pt-5">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700" aria-hidden="true">
            <i class="bi bi-trash3"></i>
        </span>
        <div class="min-w-0">
            <h2 id="konfirmasi-hapus-judul" class="text-base font-semibold">Hapus data ini?</h2>
            <p id="konfirmasi-hapus-pesan" class="mt-1 text-gray-600"></p>
            <p class="mt-2 text-gray-600">Tindakan ini tidak bisa dibatalkan.</p>
        </div>
    </div>
    <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-3">
        <button type="button" data-batal autofocus
            class="flex min-h-11 items-center gap-1.5 rounded-md border border-gray-450 bg-white px-3.5 font-medium text-gray-800 hover:bg-gray-50 active:bg-gray-100 lg:min-h-9">
            <i class="bi bi-x-lg text-xs" aria-hidden="true"></i> Batal
        </button>
        <button type="button" data-hapus
            class="flex min-h-11 items-center gap-1.5 rounded-md bg-red-700 px-3.5 font-medium text-white shadow-xs hover:bg-red-800 active:bg-red-900 disabled:bg-gray-200 disabled:text-gray-600 lg:min-h-9">
            <i class="bi bi-trash3" aria-hidden="true"></i> <span>Ya, hapus</span>
        </button>
    </div>
</dialog>

<script>
    (() => {
        const dialog = document.getElementById('konfirmasi-hapus');
        const tombolHapus = dialog.querySelector('[data-hapus]');
        let formTertunda = null;

        // Tahan pengiriman form ber-data-konfirmasi, tampilkan modal.
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form.dataset.konfirmasi) return;
            e.preventDefault();
            formTertunda = form;
            document.getElementById('konfirmasi-hapus-pesan').textContent = form.dataset.konfirmasi;
            dialog.showModal();
        });

        dialog.querySelector('[data-batal]').addEventListener('click', () => dialog.close());

        // Klik di latar gelap (di luar kartu) menutup modal.
        dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

        tombolHapus.addEventListener('click', () => {
            tombolHapus.disabled = true;
            tombolHapus.querySelector('span').textContent = 'Menghapus…';
            formTertunda.submit(); // submit() tidak memicu event submit lagi
        });
    })();
</script>

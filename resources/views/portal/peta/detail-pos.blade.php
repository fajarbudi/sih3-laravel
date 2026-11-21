        {{-- Jendela detail pos (seperti modal detilpos portal live). Isi dimuat dari route portal.pos.ringkas oleh peta.js. --}}
        <dialog id="detail-pos" aria-labelledby="detail-pos-judul"
            class="m-auto max-h-[calc(100%-2rem)] w-[min(56rem,calc(100%-2rem))] overflow-hidden rounded-3xl bg-white p-0 text-gray-900 shadow-2xl backdrop:bg-gray-950/50 backdrop:backdrop-blur-sm">
            <div class="flex max-h-[calc(100vh-2rem)] flex-col">
                <div class="flex items-center gap-3.5 px-5 pb-3 pt-5 sm:px-7 sm:pt-6">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-50 ring-1 ring-primary-100">
                        <img id="detail-pos-ikon" src="" alt="" width="28" height="32" class="h-8 w-7">
                    </span>
                    <div class="min-w-0 flex-1">
                        <p id="detail-pos-jenis" class="text-xs font-bold uppercase tracking-wider text-primary-600"></p>
                        <h2 id="detail-pos-judul" class="mt-0.5 text-xl font-extrabold leading-snug tracking-tight text-gray-950 sm:text-2xl"></h2>
                    </div>
                    <button type="button" data-tutup aria-label="Tutup" class="-mr-1 flex size-10 shrink-0 items-center justify-center self-start rounded-full bg-gray-100 text-gray-700 hover:bg-gray-200 hover:text-gray-950">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
                <div id="detail-pos-isi" class="overflow-y-auto px-5 pb-6 pt-2 sm:px-7 sm:pb-7"></div>
            </div>
        </dialog>

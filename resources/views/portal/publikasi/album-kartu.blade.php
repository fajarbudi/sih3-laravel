{{-- Kartu album (kartu samping dan tiga kartu di album.blade.php). Variabel: $d, $ringkas (tanpa keterangan). --}}
<button type="button" data-jendela="album-{{ $d->album->id }}" data-judul="{{ $d->album->nama }}"
    class="group flex flex-col overflow-hidden rounded-lg bg-white text-left shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-gray-950/5 motion-reduce:transition-none">
    <span class="block aspect-[16/10] w-full overflow-hidden bg-gray-100">
        @if ($d->sampul) <img src="{{ $d->sampul->urlKecil() }}" alt="" width="480" height="300" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-[1.03] motion-reduce:transition-none"> @endif
    </span>
    <span class="flex flex-1 flex-col p-5">
        <span class="text-xs font-bold uppercase tracking-wider text-primary-600">{{ $d->album->jenis }} <span class="font-semibold normal-case tracking-normal text-gray-500">· {{ $d->tanggal }}</span></span>
        <span class="mt-1.5 block font-bold leading-snug text-gray-950 group-hover:text-primary-700">{{ $d->album->nama }}</span>
        @unless ($ringkas)
            <span class="mt-2 line-clamp-4 text-sm text-gray-600">{{ $d->keterangan }}</span>
        @endunless
    </span>
</button>

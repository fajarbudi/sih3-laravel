/*
 * Peta sebaran pos di beranda portal (Leaflet, dibundel Vite; tidak ada skrip pihak ketiga).
 * Data titik dari <script type="application/json" id="data-peta"> (lihat PortalController::titik).
 *
 * Ikon seperti beranda lama: piktogram per jenis pos; pos yang punya status terkini (hujan 24 jam, status duga air,
 * kelas PIj) memakai ikon berwarna status.
 * Dua mode: "semua" = semua pos; "peringatan" = hanya pos yang punya status terkini.
 * Klik pos membuka jendela detail (HTML dari server); teks lain di peta dibangun dengan textContent, bukan innerHTML.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Ikon penanda dari aplikasi lama: {jenis}.png, atau {jenis}-{kelas}.png bila pos punya status terkini.
const BERKAS = { awlr: 'dugaair', kualitas_air: 'kualitasair', arr: 'curahhujan', klimatologi: 'klimatologi', mat: 'mat' };

const wadah = document.getElementById('peta-leaflet');
const titik = JSON.parse(document.getElementById('data-peta').textContent);
const elJumlah = document.getElementById('jumlah-titik');
const elKosong = document.getElementById('peta-kosong');
// Halaman Pos Pemantauan = peta layar penuh: gulir/geser langsung menggerakkan peta (seperti halaman live).
// Beranda: peta di tengah halaman, jadi tidak boleh menahan gulir halaman.
const layarPenuh = 'layarPenuh' in wadah.dataset;

// Legenda tertutup di ponsel agar peta yang sempit tidak tertutup; terbuka di layar lebar.
const legenda = document.getElementById('legenda');
if (legenda && matchMedia('(max-width: 639px)').matches) legenda.open = false;

// zoomSnap 0,25: di layar sempit fitBounds tidak melompat ke zoom yang jauh lebih kecil (wilayah memanjang barat-timur).
// Beranda mengikuti portal live: desktop zoom = Ctrl/Cmd + gulir; ponsel satu jari menggulir halaman, dua jari menggeser peta.
const peta = L.map(wadah, { scrollWheelZoom: layarPenuh, zoomSnap: 0.25, zoomControl: false, dragging: layarPenuh || !L.Browser.mobile });
L.control.zoom({ position: 'bottomright', zoomInTitle: 'Perbesar', zoomOutTitle: 'Perkecil' }).addTo(peta);
// Ukuran wadah bisa berubah tanpa resize jendela (mis. lembar filter di ponsel dibuka): beri tahu Leaflet.
new ResizeObserver(() => peta.invalidateSize()).observe(wadah);
const jalan = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
}).on('tileerror', () => document.getElementById('peta-galat')?.removeAttribute('hidden')).addTo(peta);
const satelit = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 18,
    attribution: 'Citra &copy; Esri',
});

// Petunjuk di atas peta saat pengguna mencoba menggulir/menggeser tanpa cara yang benar.
const petunjuk = document.createElement('div');
petunjuk.className = 'peta-petunjuk';
petunjuk.setAttribute('aria-hidden', 'true');
wadah.parentElement.append(petunjuk);
let waktuPetunjuk;
function tampilkanPetunjuk(pesan) {
    petunjuk.textContent = pesan;
    petunjuk.classList.add('tampil');
    clearTimeout(waktuPetunjuk);
    waktuPetunjuk = setTimeout(() => petunjuk.classList.remove('tampil'), 1400);
}
if (layarPenuh) {
    // Layar penuh: tanpa petunjuk, gulir dan geser langsung menggerakkan peta.
} else if (L.Browser.mobile) {
    wadah.addEventListener('touchmove', e => {
        if (e.touches.length === 1) tampilkanPetunjuk('Gunakan dua jari untuk menggeser peta');
    }, { passive: true });
} else {
    const tombol = /Mac/.test(navigator.platform) ? '⌘' : 'Ctrl';
    let sisa = 0;
    wadah.addEventListener('wheel', e => {
        if (!e.ctrlKey && !e.metaKey) return tampilkanPetunjuk(`Tekan ${tombol} + gulir untuk zoom peta`);
        e.preventDefault(); // cegah zoom halaman browser
        petunjuk.classList.remove('tampil');
        // Kumpulkan delta: satu takik roda mouse ~100, cubit touchpad datang bertahap kecil.
        sisa += e.deltaMode === 1 ? e.deltaY * 33 : e.deltaY;
        if (Math.abs(sisa) < 60) return;
        const arah = sisa < 0 ? 1 : -1;
        sisa = 0;
        peta.setZoomAround(peta.mouseEventToContainerPoint(e), peta.getZoom() + arah);
    }, { passive: false });
}

// Tombol Peta/Satelit (beranda kiri bawah; layar penuh di atas tombol zoom); gambar kecilnya = tampilan tujuan (gaya .peta-dasar di app.css).
const TombolDasar = L.Control.extend({
    options: { position: layarPenuh ? 'bottomright' : 'bottomleft' },
    onAdd() {
        const b = L.DomUtil.create('button', 'peta-dasar');
        b.type = 'button';
        const label = L.DomUtil.create('span', '', b);
        const render = () => {
            const keSatelit = peta.hasLayer(jalan);
            b.classList.toggle('ke-satelit', keSatelit);
            label.textContent = keSatelit ? 'Satelit' : 'Peta';
            b.setAttribute('aria-label', keSatelit ? 'Tampilkan peta satelit' : 'Tampilkan peta jalan');
        };
        L.DomEvent.disableClickPropagation(b);
        L.DomEvent.on(b, 'click', () => {
            const [lama, baru] = peta.hasLayer(jalan) ? [jalan, satelit] : [satelit, jalan];
            peta.removeLayer(lama);
            baru.addTo(peta).bringToBack();
            render();
        });
        render();
        return b;
    },
});
new TombolDasar().addTo(peta);

// Sisakan ruang untuk lapisan yang menutupi peta agar tidak ada pos tertutup: legenda terbuka di bagian bawah,
// kotak cari desa selebar peta di ponsel (atas), dan panel filter mengambang di kiri (halaman layar penuh).
function pasKe(batas) {
    const cari = document.getElementById('cari-desa');
    const panel = document.getElementById('panel-peta');
    const tinggi = wadah.clientHeight;
    const bawah = legenda?.open && legenda.offsetTop > tinggi / 2 ? tinggi - legenda.offsetTop + 16 : 0;
    const atas = innerWidth < 640 && cari ? cari.offsetTop + cari.offsetHeight : 0;
    const kiri = panel && getComputedStyle(panel).position === 'absolute' ? panel.offsetLeft + panel.offsetWidth : 0;
    peta.fitBounds(batas, { paddingTopLeft: [24 + kiri, 24 + atas], paddingBottomRight: [24, 24 + bawah] });
}

if (titik.length) {
    pasKe(L.latLngBounds(titik.map(t => [t.lat, t.lng])));
} else {
    peta.setView([-7.6, 110.2], 9); // WS Serayu Opak
}

// Jendela detail pos (seperti modal detilpos portal live): klik/Enter pada pos membuka <dialog id="detail-pos">,
// isinya HTML dari route portal.pos.ringkas (Blade di server kita, teks sudah di-escape).
const dialog = document.getElementById('detail-pos');
const isiDetail = document.getElementById('detail-pos-isi');
let permintaan;
function pesanDetail(teks) {
    const p = document.createElement('p');
    p.className = 'py-12 text-center text-gray-600';
    p.textContent = teks;
    isiDetail.replaceChildren(p);
}
function bukaDetail(t) {
    document.getElementById('detail-pos-judul').textContent = t.nama;
    document.getElementById('detail-pos-jenis').textContent = t.jenis;
    document.getElementById('detail-pos-ikon').src = ikon(t).options.iconUrl;
    pesanDetail('Memuat detail pos…');
    dialog.showModal();
    permintaan?.abort();
    permintaan = new AbortController();
    fetch(wadah.dataset.detail.replace('__ID__', t.id), { signal: permintaan.signal, headers: { Accept: 'text/html' } })
        .then(r => { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(html => { isiDetail.innerHTML = html; isiDetail.scrollTop = 0; })
        .catch(e => e.name !== 'AbortError' && pesanDetail('Detail pos gagal dimuat. Tutup lalu coba lagi.'));
}
dialog.querySelector('[data-tutup]').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', e => e.target === dialog && dialog.close()); // klik di latar gelap

const ikonCache = {};
function ikon(t) {
    // Status telemetri (aktif/tidak mengirim data/manual) tidak ditampilkan di penanda maupun jendela detail (permintaan user).
    const nama = BERKAS[t.kel] + (t.kelas ? '-' + t.kelas : '');
    return ikonCache[nama] ??= L.icon({
        iconUrl: `${wadah.dataset.ikon}/${nama}.png`,
        iconSize: [28, 32],
        iconAnchor: [14, 32],
        popupAnchor: [0, -30],
    });
}

// Batas DAS (dirampingkan dari das_2025.geojson aplikasi lama), tampil di bawah penanda; tombol [data-lapisan=das] menyalakan/mematikan.
const das = L.geoJSON(null, {
    style: { color: '#1d4ed8', weight: 1.5, fillColor: '#3b82f6', fillOpacity: 0.08 },
    // Seperti portal live: klik area DAS membuka nama DAS.
    onEachFeature: (f, layer) => layer.bindPopup(() => {
        const p = document.createElement('p');
        p.className = 'font-semibold text-center';
        p.textContent = 'DAS ' + f.properties.nama;
        return p;
    }),
}).addTo(peta);
fetch(wadah.dataset.das).then(r => r.json()).then(data => das.addData(data)).catch(() => {});
document.querySelector('[data-lapisan=das]')?.addEventListener('click', e => {
    const nyala = !peta.hasLayer(das);
    nyala ? das.addTo(peta) : peta.removeLayer(das);
    e.currentTarget.setAttribute('aria-pressed', String(nyala));
});

const lapisan = L.layerGroup().addTo(peta);
const keadaan = {
    mode: 'semua',
    kel: new Set([...document.querySelectorAll('[data-filter-kel]')].filter(k => k.checked).map(k => k.dataset.filterKel)),
    wid: '',
    pusat: null, // desa hasil pencarian: { latlng, nama }; hanya pos dalam RADIUS yang tampil
};
const RADIUS = 10000; // meter, sama dengan portal live

function gambar() {
    lapisan.clearLayers();
    const jarak = t => keadaan.pusat ? peta.distance(keadaan.pusat.latlng, [t.lat, t.lng]) : 0;
    const tampil = titik.filter(t => keadaan.kel.has(t.kel)
        && (!keadaan.wid || String(t.wid) === keadaan.wid)
        && (keadaan.mode === 'semua' || t.kelas)
        && jarak(t) <= RADIUS);
    tampil.forEach(t => {
        const m = L.marker([t.lat, t.lng], { icon: ikon(t), title: t.nama, alt: t.nama, keyboard: true })
            .on('click', () => bukaDetail(t))
            .addTo(lapisan);
        if (keadaan.pusat) m.bindTooltip(`${(jarak(t) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} km dari ${keadaan.pusat.nama}`, { direction: 'bottom' });
    });
    elJumlah.textContent = tampil.length.toLocaleString('id-ID');
    elKosong.hidden = tampil.length > 0;
    if (keadaan.pusat) {
        elInfo.textContent = `${tampil.length.toLocaleString('id-ID')} pos dalam radius 10 km dari ${keadaan.pusat.nama}.`;
        elInfo.hidden = false;
    } else {
        elInfo.hidden = true;
    }
}

// ---- Cari desa ----
const isian = document.getElementById('cari-desa-isian');
const elHasil = document.getElementById('cari-desa-hasil');
const elHapus = document.getElementById('cari-desa-hapus');
const elInfo = document.getElementById('cari-desa-info');
const lingkaran = L.circle([0, 0], { radius: RADIUS, color: '#ca8a04', weight: 2, fillColor: '#facc15', fillOpacity: 0.1, interactive: false });
const titikPusat = L.circleMarker([0, 0], { radius: 6, color: '#dc2626', weight: 2, fillColor: '#fff', fillOpacity: 1, interactive: false });

// Baris desa.json: [desa, kecamatan, kabupaten, lat, lng]; diunduh sekali saat pertama dibutuhkan.
let desa;
const muatDesa = () => desa ??= fetch(wadah.dataset.desa).then(r => r.json())
    .then(d => d.map(x => ({ nama: x[0], wilayah: `${x[1]}, ${x[2]}`, cari: `${x[0]}, ${x[1]}, ${x[2]}`.toUpperCase(), latlng: L.latLng(x[3], x[4]) })))
    .catch(() => { desa = undefined; return []; });

let pilihan = [];
let aktif = -1;
function tutupHasil() {
    elHasil.hidden = true;
    isian.setAttribute('aria-expanded', 'false');
    isian.removeAttribute('aria-activedescendant');
    aktif = -1;
}
function tampilkanHasil() {
    elHasil.replaceChildren(...(pilihan.length ? pilihan.map((d, i) => {
        const li = document.createElement('li');
        li.id = 'desa-' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', String(i === aktif));
        li.className = 'cursor-pointer rounded-lg px-3 py-2 hover:bg-gray-50 aria-selected:bg-primary-50';
        const nama = document.createElement('span');
        nama.className = 'block font-semibold text-gray-900';
        nama.textContent = d.nama;
        const wilayah = document.createElement('span');
        wilayah.className = 'block text-xs text-gray-600';
        wilayah.textContent = d.wilayah;
        li.append(nama, wilayah);
        li.addEventListener('mousedown', e => { e.preventDefault(); pilihDesa(d); });
        return li;
    }) : [Object.assign(document.createElement('li'), { className: 'px-3 py-2 text-gray-600', textContent: 'Desa tidak ditemukan.' })]));
    elHasil.hidden = false;
    isian.setAttribute('aria-expanded', 'true');
    aktif >= 0 ? isian.setAttribute('aria-activedescendant', 'desa-' + aktif) : isian.removeAttribute('aria-activedescendant');
    document.getElementById('desa-' + aktif)?.scrollIntoView({ block: 'nearest' });
}
function pilihDesa(d) {
    isian.value = `${d.nama}, ${d.wilayah}`;
    tutupHasil();
    keadaan.pusat = { latlng: d.latlng, nama: 'Desa ' + d.nama };
    lingkaran.setLatLng(d.latlng).addTo(peta);
    titikPusat.setLatLng(d.latlng).addTo(peta);
    pasKe(lingkaran.getBounds());
    gambar();
}
function hapusPencarian() {
    isian.value = '';
    elHapus.hidden = true;
    tutupHasil();
    if (!keadaan.pusat) return;
    keadaan.pusat = null;
    peta.removeLayer(lingkaran);
    peta.removeLayer(titikPusat);
    gambar();
}

isian.addEventListener('focus', muatDesa, { once: true });
isian.addEventListener('input', async () => {
    elHapus.hidden = !isian.value;
    const q = isian.value.trim().toUpperCase();
    if (q.length < 2) return tutupHasil();
    const data = await muatDesa();
    if (isian.value.trim().toUpperCase() !== q) return; // sudah diketik ulang
    pilihan = data.filter(d => d.cari.includes(q)).slice(0, 8);
    aktif = -1;
    tampilkanHasil();
});
isian.addEventListener('keydown', e => {
    if (e.key === 'Escape') return elHasil.hidden ? hapusPencarian() : tutupHasil();
    if (elHasil.hidden || !pilihan.length) return;
    const arah = { ArrowDown: 1, ArrowUp: -1 }[e.key];
    if (arah) {
        e.preventDefault();
        aktif = (aktif + arah + pilihan.length) % pilihan.length;
        tampilkanHasil();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        pilihDesa(pilihan[Math.max(aktif, 0)]);
    }
});
isian.addEventListener('blur', tutupHasil);
elHapus.addEventListener('click', () => { hapusPencarian(); isian.focus(); });

document.querySelectorAll('[data-mode]').forEach(tombol => {
    tombol.addEventListener('click', () => {
        keadaan.mode = tombol.dataset.mode;
        document.querySelectorAll('[data-mode]').forEach(b => b.setAttribute('aria-pressed', String(b === tombol)));
        document.querySelectorAll('[data-legenda]').forEach(l => { l.hidden = l.dataset.legenda !== keadaan.mode; });
        gambar();
    });
});
document.querySelectorAll('[data-filter-kel]').forEach(kotak => {
    kotak.addEventListener('change', () => {
        kotak.checked ? keadaan.kel.add(kotak.dataset.filterKel) : keadaan.kel.delete(kotak.dataset.filterKel);
        gambar();
    });
});
document.getElementById('filter-walidata')?.addEventListener('change', e => {
    keadaan.wid = e.target.value;
    gambar();
});

document.getElementById('peta-memuat')?.remove();
gambar();

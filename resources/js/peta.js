/*
 * Peta sebaran pos di beranda portal (Leaflet, dibundel Vite; tidak ada skrip pihak ketiga).
 * Data titik dari <script type="application/json" id="data-peta"> (lihat PortalController::titik).
 *
 * Ikon seperti beranda lama: piktogram per jenis pos; pos yang punya status terkini (hujan 24 jam, status duga air,
 * kelas PIj) memakai ikon berwarna status.
 * Dua mode: "semua" = semua pos; "peringatan" = hanya pos yang punya status terkini.
 * Isi popup dibangun dengan textContent, bukan innerHTML, agar nama dari database tidak bisa menyisipkan HTML.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Ikon penanda dari aplikasi lama: {jenis}.png, atau {jenis}-{kelas}.png bila pos punya status terkini.
const BERKAS = { awlr: 'dugaair', kualitas_air: 'kualitasair', arr: 'curahhujan', klimatologi: 'klimatologi', mat: 'mat' };

const wadah = document.getElementById('peta-leaflet');
const titik = JSON.parse(document.getElementById('data-peta').textContent);
const elJumlah = document.getElementById('jumlah-titik');
const elKosong = document.getElementById('peta-kosong');

// zoomSnap 0,25: di layar sempit fitBounds tidak melompat ke zoom yang jauh lebih kecil (wilayah memanjang barat-timur).
// Tombol zoom di kanan bawah: pojok kiri atas dipakai tombol mode tampilan di beranda.
const peta = L.map(wadah, { scrollWheelZoom: false, zoomSnap: 0.25, zoomControl: false });
L.control.zoom({ position: 'bottomright' }).addTo(peta);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
}).on('tileerror', () => document.getElementById('peta-galat')?.removeAttribute('hidden')).addTo(peta);

// Gulir untuk zoom hanya setelah peta diklik, agar halaman tetap bisa digulir melewati peta.
wadah.addEventListener('click', () => peta.scrollWheelZoom.enable(), { once: true });

if (titik.length) {
    peta.fitBounds(L.latLngBounds(titik.map(t => [t.lat, t.lng])), { padding: [24, 24] });
} else {
    peta.setView([-7.6, 110.2], 9); // WS Serayu Opak
}

function baris(induk, label, nilai, kelas = '') {
    if (!nilai) return;
    const p = document.createElement('p');
    if (kelas) p.className = kelas;
    const b = document.createElement('span');
    b.className = 'text-gray-600';
    b.textContent = label + ': ';
    p.append(b, nilai);
    induk.append(p);
}

function isiPopup(t) {
    const div = document.createElement('div');
    div.className = 'space-y-0.5 text-sm';
    const judul = document.createElement('p');
    judul.className = 'font-semibold text-base';
    judul.textContent = t.nama;
    div.append(judul);
    baris(div, 'Jenis', t.jenis);
    baris(div, 'Walidata', t.walidata);
    baris(div, t.label, t.nilai ?? 'belum ada data');
    baris(div, 'Hujan 24 jam', t.hujan24);
    baris(div, 'Waktu', t.waktu);
    baris(div, 'Status', { online: 'Telemetri, mengirim data', offline: 'Telemetri, tidak mengirim data > 24 jam', manual: 'Pengamatan manual' }[t.status]);
    baris(div, 'Kelas', t.kelasLabel, 'font-semibold');
    return div;
}

const ikonCache = {};
function ikon(t) {
    // Telemetri yang tidak mengirim data tidak dibedakan di penanda (permintaan user); statusnya tetap tertulis di popup.
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
    onEachFeature: (f, layer) => layer.bindTooltip('DAS ' + f.properties.nama, { sticky: true }),
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
};

function gambar() {
    lapisan.clearLayers();
    const tampil = titik.filter(t => keadaan.kel.has(t.kel)
        && (!keadaan.wid || String(t.wid) === keadaan.wid)
        && (keadaan.mode === 'semua' || t.kelas));
    tampil.forEach(t => {
        L.marker([t.lat, t.lng], { icon: ikon(t), title: t.nama, alt: t.nama, keyboard: true })
            .bindPopup(() => isiPopup(t))
            .addTo(lapisan);
    });
    elJumlah.textContent = tampil.length.toLocaleString('id-ID');
    elKosong.hidden = tampil.length > 0;
}

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

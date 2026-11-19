<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JenisPos;
use App\Http\Controllers\Controller;
use App\Models\PengamatanArr;
use App\Models\PengamatanDebit;
use App\Models\Pos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Input data harian setahun dengan menempel tabel dari Excel (asal: Input.php). Seperti aplikasi lama:
 * pilih pos dan tahun, tempel baris "tanggal <tab> Jan <tab> ... <tab> Des", pratinjau, lalu simpan; setiap nilai
 * disimpan sebagai pengamatan jam 07.00 (yang sudah ada diperbarui). Untuk curah hujan, PDF arsip tahun itu
 * dibuat ulang setelah disimpan.
 * - curah-hujan: pos ARR -> pengamatan_arr.hujan
 * - debit: pos AWLR -> pengamatan_debit.debit
 *
 * Perbedaan dengan aplikasi lama: nilai 0 ikut disimpan (dulu dilewati), input debit berfungsi (dulu menyimpan
 * ke kolom arr_* di tabel debit sehingga gagal), tanggal yang tidak ada (mis. 30 Februari) dilewati dan dilaporkan,
 * dan pratinjau tidak memakai file sementara bersama (dulu file/input.json dipakai semua pengguna).
 */
class InputController extends Controller
{
    public const JENIS = [
        // maks = kapasitas kolom (hujan decimal(8,2), debit decimal(10,3)).
        'curah-hujan' => ['judul' => 'Input Curah Hujan', 'pos' => JenisPos::Arr, 'model' => PengamatanArr::class, 'kolom' => 'hujan', 'satuan' => 'mm', 'maks' => 999999.99],
        'debit' => ['judul' => 'Input Debit', 'pos' => JenisPos::Awlr, 'model' => PengamatanDebit::class, 'kolom' => 'debit', 'satuan' => 'm³/s', 'maks' => 9999999.999],
    ];

    public function create(Request $request): View
    {
        return $this->halaman($request, []);
    }

    /** Pratinjau hasil tempel sebelum disimpan. */
    public function pratinjau(Request $request): View
    {
        $data = $this->validasi($request);

        $maks = self::JENIS[$request->route('jenis')]['maks'];

        return $this->halaman($request, $data + ['hasil' => $this->urai($data['tempel'], (int) $data['tahun'], $maks)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $jenis = $request->route('jenis');
        $input = self::JENIS[$jenis];
        $data = $this->validasi($request);
        $hasil = $this->urai($data['tempel'], (int) $data['tahun'], $input['maks']);
        $pos = Pos::findOrFail($data['pos_id']);

        DB::transaction(function () use ($input, $hasil, $pos, $request) {
            foreach ($hasil['nilai'] as $tanggal => $nilai) {
                $input['model']::updateOrCreate(
                    ['pos_id' => $pos->id, 'waktu' => Carbon::parse($tanggal)->setTime(7, 0)],
                    [$input['kolom'] => $nilai, 'diinput_oleh' => $request->user()->id],
                );
            }
        });
        if ($jenis === 'curah-hujan' && $hasil['nilai']) {
            app(FileArsipController::class)->buatPdf($pos, (int) $data['tahun']);
        }

        return to_route('admin.input.'.$jenis)->with('pesan', count($hasil['nilai']).' data '.$pos->nama.' tahun '.$data['tahun'].' disimpan.'
            .($jenis === 'curah-hujan' && $hasil['nilai'] ? ' PDF arsip diperbarui.' : ''));
    }

    private function halaman(Request $request, array $isi): View
    {
        $jenis = $request->route('jenis');

        return view('admin.input', $isi + [
            'jenis' => $jenis,
            'input' => self::JENIS[$jenis],
            'daftarPos' => $this->posTerlihat($request)->orderBy('nama')->pluck('nama', 'id'),
        ]);
    }

    /** @return array{pos_id: string, tahun: string, tempel: string} */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'pos_id' => ['required', 'integer', fn ($a, $v, $gagal) => $this->posTerlihat($request)->whereKey($v)->exists() ?: $gagal('Pos tidak ditemukan atau bukan pos Anda.')],
            'tahun' => ['required', 'integer', 'between:2000,'.now()->year],
            'tempel' => ['required', 'string', 'max:20000'],
        ], [], ['pos_id' => 'pos', 'tempel' => 'data tempelan']);
    }

    /**
     * Urai tempelan Excel: tiap baris "tanggal, Jan, ..., Des" (dipisah tab, atau titik koma bila tanpa tab).
     * Angka boleh berkoma desimal ("1,5"). Sel kosong, "-", dan "x" dilewati. Nilai negatif, notasi eksponen ("1e7"),
     * teks, atau di atas kapasitas kolom ditolak dan dilaporkan (tidak menggagalkan penyimpanan seluruhnya).
     *
     * @return array{nilai: array<string, float>, grid: array<int, array<int, float|null>>, dilewati: list<string>, ditolak: list<string>}
     */
    private function urai(string $tempel, int $tahun, float $maks): array
    {
        $nilai = $grid = $dilewati = $ditolak = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($tempel)) as $baris) {
            $sel = str_contains($baris, "\t") ? explode("\t", $baris) : explode(';', $baris);
            $tanggal = (int) trim(array_shift($sel));
            if ($tanggal < 1 || $tanggal > 31) {
                continue; // baris judul atau baris ringkasan
            }
            foreach (array_slice($sel, 0, 12) as $i => $teks) {
                $bulan = $i + 1;
                $teks = str_replace(',', '.', trim($teks));
                if ($teks === '' || $teks === '-' || strtolower($teks) === 'x') {
                    continue;
                }
                if (! preg_match('/^\d+(\.\d+)?$/', $teks) || (float) $teks > $maks) {
                    $ditolak[] = $tanggal.'/'.$bulan.' ("'.trim($sel[$i]).'")';

                    continue;
                }
                if (! checkdate($bulan, $tanggal, $tahun)) {
                    $dilewati[] = $tanggal.'/'.$bulan;

                    continue;
                }
                $nilai[sprintf('%04d-%02d-%02d', $tahun, $bulan, $tanggal)] = (float) $teks;
                $grid[$tanggal][$bulan] = (float) $teks;
            }
        }
        ksort($nilai);

        return ['nilai' => $nilai, 'grid' => $grid, 'dilewati' => $dilewati, 'ditolak' => $ditolak];
    }

    private function posTerlihat(Request $request)
    {
        return Pos::terlihatOleh($request->user())->jenis(self::JENIS[$request->route('jenis')]['pos']);
    }
}

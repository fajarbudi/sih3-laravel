<?php

namespace App\Console\Commands;

use App\Enums\JenisPos;
use App\Enums\PeranPengguna;
use App\Enums\SumberTelemetri;
use App\Models\Buletin;
use App\Services\RekapTelemetri;
use Database\Seeders\ParameterKualitasAirSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Memindahkan & membersihkan data dari database lama CodeIgniter (koneksi `legacy`, db_sih3)
 * ke skema baru. Database lama hanya dibaca.
 *
 *   php artisan migrate:fresh && php artisan sih3:import
 *   php artisan sih3:import --fresh     # kosongkan tabel tujuan dulu, lalu impor ulang
 */
#[Signature('sih3:import {--fresh : Kosongkan semua tabel aplikasi sebelum impor}')]
#[Description('Impor data dari database SIH3 lama (CodeIgniter) ke skema Laravel baru')]
class ImporSih3 extends Command
{
    /** Pos yang terhapus dari ana_ref_pos tetapi datanya masih ada; dipulihkan dari salinan ana_ref_pos66. */
    private const POS_DIPULIHKAN_DARI = 'ana_ref_pos66';

    /** Pos yang tidak ada di tabel mana pun tetapi punya data: dibuat sebagai placeholder (soft-deleted). */
    private const POS_PLACEHOLDER = [
        1735 => ['jenis' => 'klimatologi', 'metode' => 'telemetri', 'nama' => 'Klimatologi Tatonas (hw 4106)'],
    ];

    /** Pemetaan logger -> pos yang sebelumnya di-hard-code di controller Api*.php. */
    private const PERANGKAT_HARDCODE = [
        // [sumber, kode_perangkat, pos_id, faktor_skala]
        ['higertech', 'HGT1465', 1730, 1],     // Apihigertech.php: ARR Kranggan
        ['higertech', 'HGT1465', 1729, 1],     // Apihigertech.php: AWLR Kranggan
        ['tatonas', '4101', 1731, 1],          // Apitatonas.php
        ['tatonas', '4102', 1732, 1],
        ['tatonas', '4104', 1733, 1],
        ['tatonas', '4105', 1734, 0.01],       // Water Level dikirim dalam cm
        ['tatonas', '4106', 1735, 1],
        ['stesy', '10366', 1903, 1],           // Apistesy.php
        ['stesy', '10367', 1904, 1],
        ['stesy', '10392', 1905, 1],
        ['login', 'SOWL024', 1900, 1],         // Apilogin.php
        ['login', 'SOWL025', 1901, 1],
    ];

    private const WALIDATA_PJT = 11;

    private Connection $src;

    private Connection $dst;

    /** @var array<int, true> */
    private array $posValid = [];

    /** @var array<int, true> */
    private array $penggunaValid = [];

    /** @var array<string, array<string, int>> laporan per langkah */
    private array $laporan = [];

    public function handle(RekapTelemetri $rekap): int
    {
        $this->src = DB::connection('legacy');
        $this->dst = DB::connection();

        try {
            $this->src->getPdo();
        } catch (Throwable $e) {
            $this->error('Tidak bisa terhubung ke database lama: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->kosongkanTabel();
        } elseif ($this->dst->table('pos')->exists()) {
            $this->error('Tabel tujuan sudah berisi data. Jalankan dengan --fresh untuk mengimpor ulang.');

            return self::FAILURE;
        }

        $mulai = microtime(true);

        $this->langkah('Walidata', fn () => $this->imporWalidata());
        $this->langkah('Pos', fn () => $this->imporPos());
        $this->langkah('Perangkat telemetri', fn () => $this->imporPerangkat());
        $this->langkah('Batas siaga & rumus debit', fn () => $this->imporBatasDanRumus());
        $this->langkah('Arsip pos', fn () => $this->imporArsip());
        $this->langkah('Pengguna & token API', fn () => $this->imporPengguna());
        $this->langkah('Pengamatan manual', fn () => $this->imporPengamatanManual());
        $this->langkah('Kualitas air', fn () => $this->imporKualitasAir());
        $this->langkah('Telemetri', fn () => $this->imporTelemetri());
        $this->langkah('Rekap per jam', function () use ($rekap) {
            $this->catat('rekap_arr_per_jam', 'dibuat', $rekap->hitungUlangArr());
            $this->catat('rekap_awlr_per_jam', 'dibuat', $rekap->hitungUlangAwlr());
        });
        $this->langkah('Permintaan data & survei', fn () => $this->imporPermintaanData());
        $this->langkah('Konten portal', fn () => $this->imporKonten());
        $this->langkah('Hapus duplikat buletin otomatis', fn () => $this->catat('buletin', 'duplikat dihapus', Buletin::hapusDuplikatOtomatis()));

        $this->tampilkanLaporan();
        $this->info(sprintf('Selesai dalam %.1f detik.', microtime(true) - $mulai));

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------------
    // Data induk
    // ---------------------------------------------------------------------

    private function imporWalidata(): void
    {
        foreach ($this->src->table('ana_ref_walidata')->orderBy('walidata_id')->get() as $r) {
            $this->dst->table('walidata')->insert([
                'id' => $r->walidata_id,
                'nama' => $this->teks($r->walidata_nama),
                'nama_panjang' => $this->teks($r->walidata_nama_panjang),
                'website' => $this->teks($r->walidata_website),
                'url_ppid' => $this->teks($r->walidata_ppid),
                'url_kontak' => $this->teks($r->walidata_kontak_a),
                'whatsapp' => $this->teks($r->walidata_wa),
                'email' => $this->teks($r->walidata_email),
                'kontak' => $this->teks($r->walidata_kontak),
                'urutan' => (int) $r->walidata_urut,
                'setujui_otomatis' => $r->walidata_autoapprove === 'Ya',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->catat('walidata', 'diimpor');
        }
    }

    private function imporPos(): void
    {
        $walidataValid = $this->dst->table('walidata')->pluck('id')->flip();

        $simpan = function (object $r, bool $dihapus) use ($walidataValid): void {
            $jenis = $this->jenisPos($r->pos_jenis ?? '');
            if (! $jenis || ! $this->teks($r->pos_nama) || ! isset($walidataValid[$r->walidata_id])) {
                $this->catat('pos', 'dilewati: jenis/nama/walidata kosong');

                return;
            }

            [$dari, $sampai] = $this->rentangTahun($r);

            $this->dst->table('pos')->insert([
                'id' => $r->pos_id,
                'walidata_id' => $r->walidata_id,
                'kode' => $this->teks($r->pos_kode ?? null),
                'nama' => $this->teks($r->pos_nama),
                'tipe' => strtolower($this->teks($r->pos_tipe) ?? '') ?: $jenis->tipe()->value,
                'jenis' => $jenis->value,
                'metode' => strtolower($this->teks($r->pos_j ?? null) ?? 'manual'),
                'tahun_pembuatan' => $this->tahun($r->pos_tahun_pembuatan ?? null),
                'latitude' => $this->koordinat($r->pos_latitude, 90),
                'longitude' => $this->koordinat($r->pos_longitude, 180),
                'elevasi' => ((int) $r->pos_p_elevasi) ?: null,
                'nomor_register' => $this->teks($r->pos_p_register),
                'wilayah_sungai' => $this->teks($r->pos_ws),
                'das' => $this->teks($r->pos_das),
                'sungai' => $this->teks($r->pos_sungai),
                'anak_sungai' => $this->teks($r->pos_sungai_anak),
                'provinsi' => $this->teks($r->pos_provinsi),
                'kabupaten' => $this->teks($r->pos_kabupaten),
                'kecamatan' => $this->teks($r->pos_kecamatan),
                'desa' => $this->teks($r->pos_desa),
                'petugas_nama' => $this->teks($r->pos_p_nama),
                'petugas_nip' => $this->teks($r->pos_p_nip),
                'petugas_telepon' => $this->teks($r->pos_p_telp),
                'data_tersedia_dari' => $dari,
                'data_tersedia_sampai' => $sampai,
                'aktif' => ! $dihapus,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => $dihapus ? now() : null,
            ]);
            $this->catat('pos', $dihapus ? 'dipulihkan (soft-deleted)' : 'diimpor');
        };

        foreach ($this->src->table('ana_ref_pos')->orderBy('pos_id')->get() as $r) {
            $simpan($r, false);
        }

        // Pos yang datanya masih dirujuk tetapi barisnya sudah dihapus dari ana_ref_pos.
        $hilang = $this->posDirujukTapiHilang();
        $cadangan = $this->src->table(self::POS_DIPULIHKAN_DARI)->whereIn('pos_id', $hilang)->get()->keyBy('pos_id');

        foreach ($hilang as $id) {
            if (isset($cadangan[$id])) {
                $r = $cadangan[$id];
                // Jenis kosong di cadangan: tebak dari tabel data yang merujuknya.
                if (! $this->jenisPos($r->pos_jenis)) {
                    $r->pos_jenis = $this->tebakJenisDariData($id) ?? '';
                }
                $simpan($r, true);
            } elseif (isset(self::POS_PLACEHOLDER[$id])) {
                $this->buatPosPlaceholder($id, self::POS_PLACEHOLDER[$id]);
            } elseif ($jenis = $this->tebakJenisDariData($id)) {
                $this->buatPosPlaceholder($id, ['jenis' => $jenis, 'metode' => 'manual', 'nama' => "Pos #{$id} (tidak dikenal)"]);
            }
        }

        $this->posValid = $this->dst->table('pos')->pluck('id')->flip()->map(fn () => true)->all();
    }

    private function buatPosPlaceholder(int $id, array $info): void
    {
        $jenis = JenisPos::from($info['jenis']);

        $this->dst->table('pos')->insert([
            'id' => $id,
            'walidata_id' => 1,
            'nama' => '[Dipulihkan] '.$info['nama'],
            'tipe' => $jenis->tipe()->value,
            'jenis' => $jenis->value,
            'metode' => $info['metode'],
            'aktif' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);
        $this->catat('pos', 'placeholder dibuat (soft-deleted, walidata=1)');
    }

    /** @return list<int> */
    private function posDirujukTapiHilang(): array
    {
        $tabel = [
            'ana_data_arr', 'ana_data_awlr', 'ana_data_debit', 'ana_data_klimat', 'ana_data_mat', 'ana_data_kualitas_air',
            'ana_data_hidromet', 'ana_telemetri_arr', 'ana_telemetri_awlr', 'ana_higertech_arr', 'ana_higertech_awlr',
            'ana_arr_tatonas', 'ana_awlr_tatonas', 'ana_awlr_stesy', 'ana_awlr_login', 'ana_arr_beacon', 'ana_klimatologi_tatonas',
        ];
        $union = implode(' UNION ', array_map(fn ($t) => "SELECT DISTINCT pos_id FROM {$t}", $tabel));

        return array_map('intval', array_column($this->src->select(
            "SELECT pos_id FROM ({$union}) u WHERE pos_id > 0 AND pos_id NOT IN (SELECT pos_id FROM ana_ref_pos)"
        ), 'pos_id'));
    }

    private function tebakJenisDariData(int $posId): ?string
    {
        $peta = [
            'ana_data_arr' => 'arr', 'ana_telemetri_arr' => 'arr', 'ana_arr_beacon' => 'arr',
            'ana_data_awlr' => 'awlr', 'ana_telemetri_awlr' => 'awlr', 'ana_data_debit' => 'awlr',
            'ana_data_klimat' => 'klimatologi', 'ana_klimatologi_tatonas' => 'klimatologi',
            'ana_data_kualitas_air' => 'kualitas_air', 'ana_data_mat' => 'mat',
        ];
        foreach ($peta as $tabel => $jenis) {
            if ($this->src->table($tabel)->where('pos_id', $posId)->exists()) {
                return $jenis;
            }
        }

        return null;
    }

    private function imporPerangkat(): void
    {
        $posLama = $this->src->table('ana_ref_pos')->get()->keyBy('pos_id');

        $simpan = function (string $sumber, string $kode, int $posId, float $faktor, ?string $sensor = null) use ($posLama): void {
            if (! isset($this->posValid[$posId])) {
                $this->catat('perangkat_telemetri', 'dilewati: pos tidak ada');

                return;
            }
            $lama = $posLama[$posId] ?? null;
            $this->dst->table('perangkat_telemetri')->insertOrIgnore([
                'pos_id' => $posId,
                'sumber' => $sumber,
                'kode_perangkat' => $kode,
                'sensor' => $sensor,
                'faktor_skala' => $faktor,
                'aktif' => true,
                'terakhir_diterima_at' => $lama ? $this->waktu($lama->pos_last_get) : null,
                'nilai_terakhir' => $lama && $this->waktu($lama->pos_last_get) ? $lama->pos_last_val : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->catat('perangkat_telemetri', $sumber);
        };

        foreach (self::PERANGKAT_HARDCODE as [$sumber, $kode, $posId, $faktor]) {
            $simpan($sumber, $kode, $posId, $faktor);
        }

        // Sensor beacon: ref_pos.pos_tele = ID perangkat, pos_sensor = kanal sensor.
        foreach ($posLama as $p) {
            if ($this->teks($p->pos_sensor) && (int) $p->pos_tele > 0) {
                $simpan(SumberTelemetri::Beacon->value, (string) $p->pos_tele, $p->pos_id, 1, $p->pos_sensor);
            }
        }

        // PJT: pos dicocokkan dengan nama stasiun (Apipjt.php). TMA dalam meter: halaman admin lama Awlrpjt.php
        // menampilkannya apa adanya (mis. Wadaslintang 167 m = elevasi waduk); pembagian 100 di Gets.php tidak konsisten
        // (sebagian sudah dikomentari) dan tidak diikuti (keputusan user 2026-11-19).
        foreach ($posLama as $p) {
            if ((int) $p->walidata_id === self::WALIDATA_PJT && $p->pos_j === 'Telemetri') {
                $simpan(SumberTelemetri::Pjt->value, $this->teks($p->pos_nama), $p->pos_id, 1);
            }
        }
    }

    private function imporBatasDanRumus(): void
    {
        foreach ($this->src->table('ana_ref_batas')->get() as $r) {
            if (! isset($this->posValid[$r->pos_id])) {
                $this->catat('batas_siaga', 'dilewati: pos tidak ada');

                continue;
            }
            foreach (range(1, 6) as $level) {
                $nilai = $r->{'batas_'.$level};
                if ($nilai !== null) {
                    $this->dst->table('batas_siaga')->insert([
                        'pos_id' => $r->pos_id, 'level' => $level, 'nilai' => $nilai,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $this->catat('batas_siaga', 'diimpor');
                }
            }
        }

        foreach ($this->src->table('ana_ref_rumus_debit')->get() as $r) {
            if (! isset($this->posValid[$r->pos_id])) {
                $this->catat('rumus_debit', 'dilewati: pos tidak ada');

                continue;
            }
            $koefisien = array_map(fn ($i) => $r->{'rumus_debit_'.$i}, range(1, 6));
            $this->dst->table('rumus_debit')->insert([
                'pos_id' => $r->pos_id,
                'koefisien' => json_encode($koefisien),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->catat('rumus_debit', 'diimpor');
        }
    }

    private function imporArsip(): void
    {
        foreach ($this->src->table('ana_data_arsip')->orderBy('arsip_id')->get() as $r) {
            if (! isset($this->posValid[$r->pos_id]) || ! $this->teks($r->arsip_file)) {
                $this->catat('arsip_pos', 'dilewati: pos tidak ada / file kosong');

                continue;
            }
            if (! $this->tahun($r->arsip_tahun)) {
                $this->catat('arsip_pos', 'dilewati: tahun kosong (upload gagal)');

                continue;
            }
            $waktu = $this->waktu($r->arsip_waktu) ?? now();
            $this->dst->table('arsip_pos')->insert([
                'id' => $r->arsip_id,
                'pos_id' => $r->pos_id,
                'tahun' => $this->tahun($r->arsip_tahun),
                'path' => $r->arsip_file,
                'ukuran' => $this->ukuranKb($r->arsip_size),
                'status' => $this->teks($r->arsip_status),
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
            $this->catat('arsip_pos', 'diimpor');
        }
    }

    // ---------------------------------------------------------------------
    // Pengguna
    // ---------------------------------------------------------------------

    private function imporPengguna(): void
    {
        $walidataValid = $this->dst->table('walidata')->pluck('id')->flip();

        foreach ($this->src->table('ana_pengguna')->orderBy('pengguna_id')->get() as $r) {
            $walidataId = (int) $r->walidata_id ?: null;
            if ($walidataId && ! isset($walidataValid[$walidataId])) {
                $this->catat('pengguna', "walidata_id {$walidataId} tidak ada -> NULL");
                $walidataId = null;
            }
            $posId = (int) $r->pos_id ?: null;
            if ($posId && ! isset($this->posValid[$posId])) {
                $this->catat('pengguna', 'pos tidak ada -> pos_id NULL');
                $posId = null;
            }

            $md5 = preg_match('/^[0-9a-f]{32}$/', $r->password) ? $r->password : null;

            $this->dst->table('pengguna')->insert([
                'id' => $r->pengguna_id,
                'walidata_id' => $walidataId,
                'pos_id' => $posId,
                'username' => trim($r->username),
                'nama' => $this->teks($r->nama) ?? $r->username,
                'email' => $this->teks($r->email),
                'telepon' => $this->teks($r->telpon),
                'password' => null,
                'password_md5' => $md5,
                'peran' => PeranPengguna::dariLevelLama((int) $r->level)->value,
                'pengamat_nama' => $this->teks($r->p_nama),
                'pengamat_telepon' => $this->teks($r->p_telp),
                'pengamat_nik' => $this->teks($r->p_nik),
                'aktif' => $r->status === 'Aktif',
                'terakhir_login_at' => $this->waktu($r->last_login),
                'created_at' => $this->waktu($r->join_date) ?? now(),
                'updated_at' => $this->waktu($r->last_update) ?? now(),
            ]);
            $this->catat('pengguna', 'diimpor');
            if (! $md5) {
                $this->catat('pengguna', 'password bukan MD5 -> harus reset');
            }

            $token = trim((string) $r->token);
            if ($token !== '') {
                $ok = $this->dst->table('personal_access_tokens')->insertOrIgnore([
                    'tokenable_type' => 'pengguna',
                    'tokenable_id' => $r->pengguna_id,
                    'name' => 'android-lama',
                    'token' => hash('sha256', $token),
                    'expires_at' => $this->waktu($r->token_exp),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->catat('personal_access_tokens', $ok ? 'diimpor' : 'dilewati: token duplikat');
            }
        }

        $this->penggunaValid = $this->dst->table('pengguna')->pluck('id')->flip()->map(fn () => true)->all();
    }

    // ---------------------------------------------------------------------
    // Pengamatan manual
    // ---------------------------------------------------------------------

    private function imporPengamatanManual(): void
    {
        $spesifikasi = [
            // tabel lama, prefix lama, tabel baru, [kolom baru => kolom lama]
            ['ana_data_arr', 'arr', 'pengamatan_arr', ['hujan' => 'arr_hujan']],
            ['ana_data_awlr', 'awlr', 'pengamatan_awlr', [
                'tma' => 'awlr_rerata_tma', 'limpas' => 'awlr_limpas', 'intake_kiri' => 'awlr_intake_kiri',
                'intake_kanan' => 'awlr_intake_kanan', 'debit_pdam' => 'awlr_debit_pdam', 'pembilas_kiri' => 'awlr_pembilas_kiri',
                'pembilas_kanan' => 'awlr_pembilas_kanan', 'debit_suplesi' => 'awlr_debit_suplesi',
            ]],
            ['ana_data_debit', 'debit', 'pengamatan_debit', [
                'intake_kanan' => 'debit_intake_kanan', 'intake_kiri' => 'debit_intake_kiri',
                'limpas' => 'debit_limpas', 'debit' => 'debit_debit',
            ]],
            ['ana_data_klimat', 'klimat', 'pengamatan_klimatologi', [
                'kelembapan' => 'klimat_rh', 'suhu_min' => 'klimat_temp_min', 'suhu_max' => 'klimat_temp_max',
                'suhu_rata' => 'klimat_temp_rata', 'suhu_air' => 'klimat_temp_air', 'suhu_air_min' => 'klimat_temp_air_min',
                'suhu_air_max' => 'klimat_temp_air_max', 'penguapan' => 'klimat_penguapan', 'kecepatan_angin' => 'klimat_kec_angin',
                'radiasi' => 'klimat_radiasi', 'penyinaran' => 'klimat_penyinaran', 'hujan' => 'klimat_hujan',
            ]],
            ['ana_data_mat', 'mat', 'pengamatan_mat', ['muka_air_tanah' => 'mat_tanah']],
            ['ana_data_hidromet', 'hidromet', 'prakiraan_hidromet', [
                'normal_min' => 'hidromet_normal_min', 'normal_max' => 'hidromet_normal_max',
                'prakiraan_min' => 'hidromet_perkiraan_min', 'prakiraan_max' => 'hidromet_perkiraan_max',
                'sifat' => 'hidromet_sifat',
            ]],
        ];

        foreach ($spesifikasi as [$asal, $p, $tujuan, $kolom]) {
            // Urut dari id terbesar: untuk duplikat (pos_id, waktu), baris terbaru yang dipakai.
            $this->src->table($asal)->chunkByIdDesc(2000, function ($rows) use ($p, $tujuan, $kolom) {
                $batch = [];
                foreach ($rows as $r) {
                    if (! isset($this->posValid[$r->pos_id])) {
                        $this->catat($tujuan, 'dilewati: pos tidak ada');

                        continue;
                    }
                    $waktu = $this->waktuPengamatan($r->{$p.'_date'}, $r->{$p.'_jam'}, $r->{$p.'_waktu'});
                    if (! $waktu) {
                        $this->catat($tujuan, 'dilewati: waktu tidak valid');

                        continue;
                    }
                    $input = $this->waktu($r->{$p.'_waktu_input'}) ?? $waktu;
                    $baris = [
                        // ID lama dipertahankan: aplikasi lama mengurutkan daftar menurut urutan input (id terbesar),
                        // dan klimat/mat/kualitas_air tidak punya waktu input yang terisi.
                        'id' => $r->{$p.'_id'},
                        'pos_id' => $r->pos_id,
                        'waktu' => $waktu,
                        'keterangan' => $this->teks($r->{$p.'_keterangan'}),
                        'tervalidasi' => ($r->{$p.'_status'} ?? 'Ya') === 'Ya',
                        'created_at' => $input,
                        'updated_at' => $input,
                    ];
                    foreach ($kolom as $baru => $lama) {
                        $baris[$baru] = is_string($r->{$lama}) && ! is_numeric($r->{$lama}) ? $this->teks($r->{$lama}) : $r->{$lama};
                    }
                    $batch[] = $baris;
                }
                $masuk = $batch ? $this->dst->table($tujuan)->insertOrIgnore($batch) : 0;
                $this->catat($tujuan, 'diimpor', $masuk);
                $this->catat($tujuan, 'dilewati: duplikat pos+waktu', count($batch) - $masuk);
            }, $p.'_id');
        }
    }

    private function imporKualitasAir(): void
    {
        (new ParameterKualitasAirSeeder)->run();
        $parameter = $this->dst->table('parameter_kualitas_air')->pluck('id', 'kode');

        $p = 'kualitas_air_';
        foreach ($this->src->table('ana_data_kualitas_air')->orderBy('kualitas_air_id')->get() as $r) {
            if (! isset($this->posValid[$r->pos_id])) {
                $this->catat('pengamatan_kualitas_air', 'dilewati: pos tidak ada');

                continue;
            }
            $waktu = $this->waktuPengamatan($r->{$p.'date'}, $r->{$p.'jam'}, $r->{$p.'waktu'});
            if (! $waktu) {
                $this->catat('pengamatan_kualitas_air', 'dilewati: waktu tidak valid');

                continue;
            }
            $input = $this->waktu($r->{$p.'waktu_input'}) ?? $waktu;

            $id = $this->dst->table('pengamatan_kualitas_air')->insertGetId([
                'id' => $r->kualitas_air_id, // ID lama dipertahankan (urutan input), lihat imporPengamatanManual
                'pos_id' => $r->pos_id,
                'waktu' => $waktu,
                'lokasi' => $this->teks($r->{$p.'lokasi'}),
                'pengukuran_debit' => $this->teks($r->{$p.'pengukuran_debit'}),
                'cuaca' => $this->teks($r->{$p.'cuaca'}),
                'hanyutan' => $this->teks($r->{$p.'hanyutan'}),
                'sedimen' => $this->teks($r->{$p.'sedimen'}),
                'bau' => $this->teks($r->{$p.'bau'}),
                'kotoran_minyak' => $this->teks($r->{$p.'kotoran_minyak'}),
                'lumut' => $this->teks($r->{$p.'lumut'}),
                'vegetasi' => $this->teks($r->{$p.'vegetasi'}),
                'keadaan_sungai' => $this->teks($r->{$p.'keadaan_sungai'}),
                'indeks_pencemaran' => $r->{$p.'ip'},
                'storet' => $r->{$p.'storet'},
                'keterangan' => $this->teks($r->{$p.'keterangan'}),
                'tervalidasi' => $r->{$p.'status'} === 'Ya',
                'created_at' => $input,
                'updated_at' => $input,
            ]);
            $this->catat('pengamatan_kualitas_air', 'diimpor');

            $nilai = [];
            foreach ($parameter as $kode => $parameterId) {
                $v = $r->{$p.$kode} ?? null;
                if ($v !== null) {
                    $nilai[] = ['pengamatan_kualitas_air_id' => $id, 'parameter_kualitas_air_id' => $parameterId, 'nilai' => $v];
                }
            }
            if ($nilai) {
                $this->dst->table('nilai_kualitas_air')->insert($nilai);
                $this->catat('nilai_kualitas_air', 'diimpor', count($nilai));
            }
        }
    }

    // ---------------------------------------------------------------------
    // Telemetri
    // ---------------------------------------------------------------------

    private function imporTelemetri(): void
    {
        $arr = fn (string $sumber, string $kolomId = 'arr_id') => [
            'tujuan' => 'telemetri_arr', 'sumber' => $sumber, 'id' => $kolomId,
            'map' => fn ($r) => [
                'waktu' => $r->arr_waktu,
                'hujan' => $r->arr_hujan,
                'hujan_kumulatif' => $r->arr_hujan_cumulative ?? null,
            ],
        ];
        $awlr = fn (string $sumber) => [
            'tujuan' => 'telemetri_awlr', 'sumber' => $sumber, 'id' => 'awlr_id',
            'map' => fn ($r) => [
                'waktu' => $r->awlr_waktu,
                'tma' => $r->awlr_tma,
                'level' => $r->awlr_level ?? null,
                'debit' => $r->awlr_debit ?? null,
            ],
        ];

        $sumber = [
            'ana_higertech_arr' => $arr('higertech'),
            'ana_arr_tatonas' => $arr('tatonas'),
            'ana_telemetri_arr' => $arr('pjt') + ['campuran' => true],
            // Beacon tersimpan per jam: tanggal (arr_waktu DATE) + arr_jam.
            'ana_arr_beacon' => [
                'tujuan' => 'telemetri_arr', 'sumber' => 'beacon', 'id' => 'arr_id',
                'map' => fn ($r) => [
                    'waktu' => sprintf('%s %02d:00:00', $r->arr_waktu, $r->arr_jam),
                    'hujan' => $r->arr_hujan,
                    'hujan_kumulatif' => null,
                    'diterima_at' => $this->waktu($r->arr_last_update) ?? now(),
                ],
            ],
            'ana_higertech_awlr' => $awlr('higertech'),
            'ana_awlr_tatonas' => $awlr('tatonas'),
            'ana_awlr_stesy' => $awlr('stesy'),
            'ana_awlr_login' => $awlr('login'),
            'ana_telemetri_awlr' => $awlr('pjt') + ['campuran' => true],
            'ana_klimatologi_tatonas' => [
                'tujuan' => 'telemetri_klimatologi', 'sumber' => 'tatonas', 'id' => 'klimatologi_id',
                'map' => fn ($r) => [
                    'waktu' => $r->klimatologi_waktu,
                    'kecepatan_angin' => $r->klimatologi_velocity,
                    'arah_angin' => $r->klimatologi_direction,
                    'radiasi' => $r->klimatologi_radiation,
                    'tekanan' => $r->klimatologi_pressure,
                    'kelembapan' => $r->klimatologi_humidity,
                    'suhu' => $r->klimatologi_temp,
                    'uv' => $r->klimatologi_uv,
                    'penguapan' => $r->klimatologi_pan,
                    'hujan' => $r->klimatologi_hujan,
                ],
            ],
        ];

        // ana_telemetri_arr/awlr di aplikasi lama adalah tabel CAMPURAN: selain PJT, juga berisi salinan identik data
        // pos vendor lain (Higertech/Tatonas/Stesy, pos BBWS). Baris pos yang punya perangkat vendor lain diberi sumber
        // vendor itu, sehingga salinan yang dobel terbuang oleh kunci unik (pos_id, waktu, sumber) dan rekap per jam
        // tidak menjumlah dua kali. Pos tanpa perangkat (mis. pos DPUP ESDM DIY) tetap berlabel pjt.
        $sumberPos = $this->dst->table('perangkat_telemetri')->where('sumber', '!=', SumberTelemetri::Pjt->value)
            ->orderBy('id')->pluck('sumber', 'pos_id');

        foreach ($sumber as $asal => $s) {
            $label = "{$s['tujuan']} <- {$asal}";
            $this->src->table($asal)->chunkById(5000, function ($rows) use ($s, $label, $sumberPos) {
                $batch = [];
                foreach ($rows as $r) {
                    if (! isset($this->posValid[$r->pos_id])) {
                        $this->catat($label, 'dilewati: pos tidak ada');

                        continue;
                    }
                    $baris = ($s['map'])($r);
                    if (! $this->waktu($baris['waktu'])) {
                        $this->catat($label, 'dilewati: waktu tidak valid');

                        continue;
                    }
                    $sumberBaris = ! empty($s['campuran']) ? ($sumberPos[$r->pos_id] ?? $s['sumber']) : $s['sumber'];
                    $batch[] = ['pos_id' => $r->pos_id, 'sumber' => $sumberBaris] + $baris + ['diterima_at' => $baris['waktu']];
                }
                $masuk = $batch ? $this->dst->table($s['tujuan'])->insertOrIgnore($batch) : 0;
                $this->catat($label, 'diimpor', $masuk);
                $this->catat($label, 'dilewati: duplikat', count($batch) - $masuk);
            }, $s['id']);
        }
    }

    // ---------------------------------------------------------------------
    // Permintaan data
    // ---------------------------------------------------------------------

    private function imporPermintaanData(): void
    {
        $permintaanPerSesi = [];

        foreach ($this->src->table('ana_data_download')->orderBy('download_id')->get() as $r) {
            $sesi = (string) $r->download_sess;
            if (! isset($permintaanPerSesi[$sesi])) {
                $waktu = $this->waktu($r->download_waktu) ?? now();
                $permintaanPerSesi[$sesi] = $this->dst->table('permintaan_data')->insertGetId([
                    'pengguna_id' => isset($this->penggunaValid[$r->pengguna_id]) ? $r->pengguna_id : null,
                    'kode_sesi' => $sesi,
                    'jenis' => $this->teks($r->download_jenis),
                    'nama_pemohon' => $this->teks($r->download_nama) ?? '-',
                    'instansi' => $this->teks($r->download_instansi),
                    'jabatan' => $this->teks($r->download_jabatan),
                    'tujuan' => $this->teks($r->download_tujuan),
                    'periode_awal' => $this->bulan($r->download_tgl_awal)?->startOfMonth()->toDateString(),
                    'periode_akhir' => $this->bulan($r->download_tgl_akhir)?->endOfMonth()->toDateString(),
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
                $this->catat('permintaan_data', 'diimpor');
            }

            if (! isset($this->posValid[$r->pos_id])) {
                $this->catat('permintaan_data_pos', 'dilewati: pos tidak ada');

                continue;
            }
            $this->dst->table('permintaan_data_pos')->insertOrIgnore([
                'permintaan_data_id' => $permintaanPerSesi[$sesi],
                'pos_id' => $r->pos_id,
                'status' => strtolower($this->teks($r->download_status) ?? 'pengajuan'),
                'keterangan' => $this->teks($r->download_keterangan),
                'path_file' => $this->teks($r->download_file),
                'token_unduh' => Str::random(32), // dipakai tautan unduh arsip di halaman detail
                'created_at' => $this->waktu($r->download_waktu) ?? now(),
                'updated_at' => $this->waktu($r->download_waktu) ?? now(),
            ]);
            $this->catat('permintaan_data_pos', 'diimpor');
        }

        if ($n = $this->src->table('ana_data_download_file')->count()) {
            $this->catat('permintaan_data_pos', "PERINGATAN: {$n} baris ana_data_download_file belum dipetakan");
        }

        foreach ($this->src->table('ana_data_skm')->orderBy('skm_id')->get() as $r) {
            $jawaban = [];
            foreach (range(8, 18) as $i) {
                if ($v = $this->teks($r->{'skm_'.$i})) {
                    $jawaban[(string) $i] = $v;
                }
            }
            $this->dst->table('survei_kepuasan')->insert([
                'id' => $r->skm_id,
                'permintaan_data_id' => $permintaanPerSesi[(string) $r->download_sess] ?? null,
                'pengguna_id' => isset($this->penggunaValid[$r->pengguna_id]) ? $r->pengguna_id : null,
                'walidata_id' => $this->dst->table('walidata')->where('id', $r->walidata_id)->exists() ? $r->walidata_id : null,
                'jenis' => $this->teks($r->skm_jenis),
                'telepon' => $this->teks($r->skm_hp),
                'email' => $this->teks($r->skm_email),
                'usia' => ((int) $r->skm_usia) ?: null,
                'jenis_kelamin' => $this->teks($r->skm_kelamin),
                'pendidikan' => $this->teks($r->skm_pendidikan),
                'jawaban' => $jawaban ? json_encode($jawaban) : null,
                'created_at' => $this->waktu($r->skm_waktu) ?? now(),
                'updated_at' => $this->waktu($r->skm_waktu) ?? now(),
            ]);
            $this->catat('survei_kepuasan', 'diimpor');
        }
    }

    // ---------------------------------------------------------------------
    // Konten portal
    // ---------------------------------------------------------------------

    private function imporKonten(): void
    {
        $kategori = fn (string $jenis, ?string $nama) => $this->kategori($jenis, $nama);
        $terbit = fn ($v) => $v === 'Ya';
        $posAtauNull = fn ($id) => isset($this->posValid[(int) $id]) ? (int) $id : null;

        foreach ($this->src->table('ana_data_buletin')->orderBy('buletin_id')->get() as $r) {
            $tanggal = $this->waktu($r->buletin_tgl) ?? $this->waktu($r->buletin_time) ?? now();
            $this->dst->table('buletin')->insert([
                'id' => $r->buletin_id,
                'kategori_id' => $kategori('buletin', $r->buletin_kategori),
                'bidang' => $this->teks($r->buletin_jenis),
                'tipe' => strtolower($this->teks($r->buletin_tipe) ?? 'manual'),
                'judul' => $this->teks($r->buletin_nama) ?? '-',
                'ringkasan' => $this->teks($r->buletin_keterangan),
                'isi' => $this->teks($r->buletin_keterangan2),
                'sumber' => $this->teks($r->buletin_sumber),
                'url_sumber' => $this->teks($r->buletin_url),
                'url_gambar_sumber' => $this->teks($r->buletin_img_ori),
                'urutan' => (int) $r->buletin_urut,
                'terbit' => $terbit($r->buletin_terbit),
                'tanggal' => $tanggal,
                'created_at' => $tanggal,
                'updated_at' => $this->waktu($r->buletin_time) ?? $tanggal,
            ]);
            $this->lampiran('buletin', $r->buletin_id, 'gambar', $r->buletin_img, $r->buletin_img_small, $r->buletin_img_ext, $r->buletin_img_size, 1);
            $this->lampiran('buletin', $r->buletin_id, 'gambar', $r->buletin_img2, $r->buletin_img2_small, $r->buletin_img2_ext, $r->buletin_img2_size, 2);
            $this->lampiran('buletin', $r->buletin_id, 'gambar', $r->buletin_img3, $r->buletin_img3_small, $r->buletin_img3_ext, $r->buletin_img3_size, 3);
            $this->lampiran('buletin', $r->buletin_id, 'pdf', $r->buletin_file_pdf, null, $r->buletin_ext_pdf, $r->buletin_size_pdf, 10);
            $this->lampiran('buletin', $r->buletin_id, 'dokumen', $r->buletin_file, null, $r->buletin_ext, null, 11);
            $this->catat('buletin', 'diimpor');
        }

        foreach ($this->src->table('ana_data_album')->orderBy('album_id')->get() as $r) {
            $this->dst->table('album')->insert([
                'id' => $r->album_id,
                'jenis' => $this->teks($r->album_jenis),
                'nama' => $this->teks($r->album_nama) ?? '-',
                'keterangan' => $this->teks($r->album_keterangan),
                'url' => $this->teks($r->album_url),
                'url_video' => $this->teks($r->album_video),
                'terbit' => $terbit($r->album_terbit),
                'tanggal' => $this->tanggal($r->album_tgl) ?? now()->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->lampiran('album', $r->album_id, 'gambar', $r->album_file, $r->album_file_small, $r->album_ext, $r->album_size, 1);
            foreach (range(2, 8) as $i) {
                $this->lampiran('album', $r->album_id, 'gambar', $r->{"album_file{$i}"}, $r->{"album_file{$i}_small"}, $r->{"album_ext{$i}"}, null, $i);
            }
            $this->lampiran('album', $r->album_id, 'pdf', $r->album_file_pdf, null, $r->album_ext_pdf, null, 10);
            $this->catat('album', 'diimpor');
        }

        foreach ($this->src->table('ana_data_galeri')->orderBy('galeri_id')->get() as $r) {
            $this->dst->table('galeri')->insert([
                'id' => $r->galeri_id,
                'pos_id' => $posAtauNull($r->pos_id),
                'nama' => $this->teks($r->galeri_nama) ?? '-',
                'keterangan' => $this->teks($r->galeri_keterangan),
                'terbit' => $terbit($r->galeri_terbit),
                'tanggal' => $this->tanggal($r->galeri_tgl) ?? now()->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->lampiran('galeri', $r->galeri_id, 'gambar', $r->galeri_file, $r->galeri_file_small, $r->galeri_ext, $r->galeri_size, 1);
            $this->catat('galeri', 'diimpor');
        }

        foreach ($this->src->table('ana_data_glosarium')->orderBy('glosarium_id')->get() as $r) {
            $this->dst->table('glosarium')->insert([
                'id' => $r->glosarium_id,
                'bidang' => $this->teks($r->glosarium_tipe),
                'istilah' => $this->teks($r->glosarium_nama) ?? '-',
                'keterangan' => $this->teks($r->glosarium_keterangan),
                'url' => $this->teks($r->glosarium_url),
                'urutan' => (int) $r->glosarium_urut,
                'terbit' => $terbit($r->glosarium_terbit),
                'tanggal' => $this->tanggal($r->glosarium_tgl),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->lampiran('glosarium', $r->glosarium_id, 'gambar', $r->glosarium_file, $r->glosarium_file_small, null, null, 1);
            $this->catat('glosarium', 'diimpor');
        }

        $buletinValid = $this->dst->table('buletin')->pluck('id')->flip();
        foreach ($this->src->table('ana_data_gpeta')->orderBy('gpeta_id')->get() as $r) {
            $this->dst->table('peta')->insert([
                'id' => $r->gpeta_id,
                'kategori_id' => $kategori('peta', $r->gpeta_jenis ?: $r->gpeta_kategori),
                'buletin_id' => isset($buletinValid[$r->buletin_id]) ? $r->buletin_id : null,
                'nama' => $this->teks($r->gpeta_nama) ?? '-',
                'keterangan' => $this->teks($r->gpeta_keterangan),
                'sumber' => $this->teks($r->gpeta_sumber),
                'url' => $this->teks($r->gpeta_url),
                'terbit' => $terbit($r->gpeta_terbit),
                'tanggal' => $this->tanggal($r->gpeta_tgl),
                'created_at' => $this->waktu($r->gpeta_time) ?? now(),
                'updated_at' => $this->waktu($r->gpeta_time) ?? now(),
            ]);
            $this->lampiran('peta', $r->gpeta_id, 'gambar', $r->gpeta_file, $r->gpeta_file_small, $r->gpeta_ext, $r->gpeta_size, 1);
            foreach (range(2, 5) as $i) {
                $this->lampiran('peta', $r->gpeta_id, 'gambar', $r->{"gpeta_file{$i}"}, $r->{"gpeta_file{$i}_small"}, $r->{"gpeta_ext{$i}"}, null, $i);
            }
            $this->lampiran('peta', $r->gpeta_id, 'gambar', $r->gpeta_file_ori, null, null, null, 9);
            $this->lampiran('peta', $r->gpeta_id, 'pdf', $r->gpeta_file_pdf, null, $r->gpeta_ext_pdf, null, 10);
            $this->catat('peta', 'diimpor');
        }

        foreach ($this->src->table('ana_data_infografis')->orderBy('infografis_id')->get() as $r) {
            $this->dst->table('infografis')->insert([
                'id' => $r->infografis_id,
                'nama' => $this->teks($r->infografis_nama) ?? '-',
                'keterangan' => $this->teks($r->infografis_keterangan),
                'url' => $this->teks($r->infografis_url),
                'terbit' => $terbit($r->infografis_terbit),
                'tanggal' => $this->tanggal($r->infografis_tgl) ?? now()->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->lampiran('infografis', $r->infografis_id, 'gambar', $r->infografis_file, $r->infografis_file_small, $r->infografis_ext, $r->infografis_size, 1);
            foreach (range(2, 5) as $i) {
                $this->lampiran('infografis', $r->infografis_id, 'gambar', $r->{"infografis_file{$i}"}, $r->{"infografis_file{$i}_small"}, $r->{"infografis_ext{$i}"}, null, $i);
            }
            $this->catat('infografis', 'diimpor');
        }

        foreach ($this->src->table('ana_data_kejadian')->orderBy('kejadian_id')->get() as $r) {
            $this->dst->table('kejadian')->insert([
                'id' => $r->kejadian_id,
                'pos_id' => $posAtauNull($r->pos_id),
                'kategori_id' => $kategori('kejadian', $r->kejadian_kategori),
                'nama' => $this->teks($r->kejadian_nama) ?? '-',
                'keterangan' => $this->teks($r->kejadian_keterangan),
                'terbit' => $terbit($r->kejadian_terbit),
                'tanggal' => $this->tanggal($r->kejadian_tgl) ?? now()->toDateString(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->lampiran('kejadian', $r->kejadian_id, 'gambar', $r->kejadian_file, $r->kejadian_file_small, $r->kejadian_ext, $r->kejadian_size, 1);
            $this->catat('kejadian', 'diimpor');
        }

        foreach ($this->src->table('ana_data_buku')->orderBy('buku_id')->get() as $r) {
            $waktu = $this->waktu($r->buku_tgl) ?? now();
            $this->dst->table('dokumen')->insert([
                'id' => $r->buku_id,
                'kategori_id' => $kategori('buku', $r->buku_jenis),
                'nama' => $this->teks($r->buku_nama) ?? '-',
                'isi' => $this->teks($r->buku_isi),
                'tahun' => $this->teks($r->buku_tahun),
                'terbit' => $terbit($r->buku_terbit),
                'created_at' => $waktu, 'updated_at' => $waktu,
            ]);
            $this->lampiran('dokumen', $r->buku_id, 'dokumen', $r->buku_file, null, null, null, 1);
            $this->catat('dokumen', 'diimpor');
        }

        foreach ($this->src->table('ana_data_pengiriman')->orderBy('pengiriman_id')->get() as $r) {
            $this->dst->table('pengiriman_air')->insert([
                'id' => $r->pengiriman_id,
                'tanggal' => $this->tanggal($r->pengiriman_tgl) ?? now()->toDateString(),
                'jumlah_terdampak' => $r->pengiriman_jumlah_terdampak,
                'jumlah_tangki' => $r->pengiriman_jumlah_tangki,
                'volume' => $r->pengiriman_volume,
                'penyedia' => $this->teks($r->pengiriman_penyedia),
                'fasilitas_umum' => $this->teks($r->pengiriman_fasum),
                'kontak' => $this->teks($r->pengiriman_kontak),
                'catatan' => $this->teks($r->pengiriman_catatan),
                'desa' => $this->teks($r->pengiriman_desa),
                'kecamatan' => $this->teks($r->pengiriman_kec),
                'kabupaten' => $this->teks($r->pengiriman_kab),
                'provinsi' => $this->teks($r->pengiriman_prov),
                'latitude' => $this->koordinat($r->pengiriman_latitude, 90),
                'longitude' => $this->koordinat($r->pengiriman_longitude, 180),
                'status' => $this->teks($r->pengiriman_status),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->lampiran('pengiriman_air', $r->pengiriman_id, 'gambar', $r->pengiriman_img, $r->pengiriman_img_small, $r->pengiriman_img_ext, $r->pengiriman_img_size, 1);
            $this->catat('pengiriman_air', 'diimpor');
        }
    }

    /** @var array<string, int> */
    private array $cacheKategori = [];

    private function kategori(string $jenis, ?string $nama): ?int
    {
        $nama = $this->teks($nama);
        if (! $nama) {
            return null;
        }
        $slug = Str::slug($nama);
        $kunci = "{$jenis}:{$slug}";

        if (! isset($this->cacheKategori[$kunci])) {
            $this->cacheKategori[$kunci] = $this->dst->table('kategori')->insertGetId([
                'jenis' => $jenis, 'nama' => $nama, 'slug' => $slug,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->catat('kategori', $jenis);
        }

        return $this->cacheKategori[$kunci];
    }

    private function lampiran(string $tipe, int $id, string $jenis, ?string $path, ?string $kecil, ?string $ekstensi, $ukuranKb, int $urutan): void
    {
        $path = $this->teks($path);
        if (! $path) {
            return;
        }
        $this->dst->table('lampiran')->insert([
            'lampiran_type' => $tipe,
            'lampiran_id' => $id,
            'jenis' => $jenis,
            'path' => $path,
            'path_kecil' => $this->teks($kecil),
            'ekstensi' => strtolower(ltrim($this->teks($ekstensi) ?? pathinfo($path, PATHINFO_EXTENSION), '.')) ?: null,
            'ukuran' => $this->ukuranKb($ukuranKb),
            'urutan' => $urutan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->catat('lampiran', $tipe);
    }

    // ---------------------------------------------------------------------
    // Pembersih nilai
    // ---------------------------------------------------------------------

    /** Trim, ganti NBSP, string kosong -> null. */
    private function teks(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(preg_replace('/\x{00A0}/u', ' ', (string) $v));

        return $v === '' ? null : $v;
    }

    /** Datetime valid (>= 1971) atau null. Menyaring 0000-00-00 dan 1970-01-01. */
    private function waktu(mixed $v): ?string
    {
        $v = $this->teks($v);
        if (! $v || str_starts_with($v, '0000')) {
            return null;
        }
        try {
            $c = Carbon::parse($v);
        } catch (Throwable) {
            return null;
        }

        return $c->year < 1971 ? null : $c->toDateTimeString();
    }

    private function tanggal(mixed $v): ?string
    {
        return ($w = $this->waktu($v)) ? substr($w, 0, 10) : null;
    }

    /**
     * Waktu pengamatan manual. Form lama mengisi *_waktu dengan TANGGAL INPUT + jam
     * (strtotime($jam) di Arr.php), jadi tanggal pengamatan yang benar ada di *_date.
     * Prioritas: *_date + *_jam, lalu *_waktu.
     */
    private function waktuPengamatan(mixed $date, mixed $jam, mixed $waktu): ?string
    {
        if ($tgl = $this->tanggal($date)) {
            return sprintf('%s %02d:00:00', $tgl, min(23, max(0, (int) $jam)));
        }

        return $this->waktu($waktu);
    }

    private function tahun(mixed $v): ?int
    {
        $v = (int) $v;

        return $v >= 1900 && $v <= 2155 ? $v : null;
    }

    private function bulan(mixed $v): ?Carbon
    {
        $v = $this->teks($v);

        return $v && preg_match('/^\d{4}-\d{2}$/', $v) ? Carbon::createFromFormat('Y-m-d', $v.'-01') : null;
    }

    private function koordinat(mixed $v, int $batas): ?float
    {
        $v = str_replace(',', '.', $this->teks($v) ?? '');
        if (! is_numeric($v) || (float) $v == 0.0 || abs((float) $v) > $batas) {
            return null;
        }

        return round((float) $v, 7);
    }

    /** Kolom *_size lama dalam KB (x1000), hasil dalam byte. */
    private function ukuranKb(mixed $v): ?int
    {
        return is_numeric($v) && $v > 0 ? (int) round($v * 1000) : null;
    }

    private function jenisPos(?string $v): ?JenisPos
    {
        return match (strtolower(trim((string) $v))) {
            'arr' => JenisPos::Arr,
            'awlr' => JenisPos::Awlr,
            'klimatologi' => JenisPos::Klimatologi,
            'kualitas air' => JenisPos::KualitasAir,
            'mat' => JenisPos::Mat,
            'agwlr' => JenisPos::Agwlr,
            default => null,
        };
    }

    /** @return array{0: ?int, 1: ?int} */
    private function rentangTahun(object $r): array
    {
        $dari = $this->tahun($r->pos_ketersediaan_awal ?? null);
        $sampai = $this->tahun($r->pos_ketersediaan_akhir ?? null);

        if ((! $dari || ! $sampai) && preg_match_all('/\d{4}/', (string) ($r->pos_ketersediaan ?? ''), $m)) {
            $tahun = array_map('intval', $m[0]);
            $dari ??= min($tahun);
            $sampai ??= max($tahun);
        }

        return [$dari, $sampai];
    }

    // ---------------------------------------------------------------------
    // Infrastruktur
    // ---------------------------------------------------------------------

    private function kosongkanTabel(): void
    {
        $kecuali = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

        Schema::disableForeignKeyConstraints();
        // getTables() tanpa argumen mendaftar tabel dari SEMUA database di server; batasi ke database tujuan.
        foreach (array_column(Schema::getTables($this->dst->getDatabaseName()), 'name') as $tabel) {
            if (! in_array($tabel, $kecuali, true)) {
                $this->dst->table($tabel)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();
        $this->warn('Semua tabel aplikasi dikosongkan.');
    }

    private function langkah(string $nama, callable $fn): void
    {
        $t = microtime(true);
        $this->output->write("  {$nama} ... ");
        $fn();
        $this->output->writeln(sprintf('<info>OK</info> (%.1fs)', microtime(true) - $t));
    }

    private function catat(string $tabel, string $hal, int $jumlah = 1): void
    {
        if ($jumlah === 0) {
            return;
        }
        $this->laporan[$tabel][$hal] = ($this->laporan[$tabel][$hal] ?? 0) + $jumlah;
    }

    private function tampilkanLaporan(): void
    {
        $baris = [];
        foreach ($this->laporan as $tabel => $hal) {
            foreach ($hal as $keterangan => $jumlah) {
                $baris[] = [$tabel, $keterangan, number_format($jumlah, 0, ',', '.')];
            }
        }
        $this->newLine();
        $this->table(['Tabel', 'Keterangan', 'Jumlah'], $baris);
    }
}

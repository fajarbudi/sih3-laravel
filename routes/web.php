<?php

use App\Http\Controllers\Admin\AmbangBatasController;
use App\Http\Controllers\Admin\CurahHujanController;
use App\Http\Controllers\Admin\CurahHujanTelemetriController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DebitController;
use App\Http\Controllers\Admin\DugaAirController;
use App\Http\Controllers\Admin\FileArsipController;
use App\Http\Controllers\Admin\InputController;
use App\Http\Controllers\Admin\KetersediaanController;
use App\Http\Controllers\Admin\KlimatologiController;
use App\Http\Controllers\Admin\KontenController;
use App\Http\Controllers\Admin\KualitasAirController;
use App\Http\Controllers\Admin\MonitoringDugaAirController;
use App\Http\Controllers\Admin\MukaAirTanahController;
use App\Http\Controllers\Admin\PenggunaController;
use App\Http\Controllers\Admin\PerangkatTelemetriController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\RekapKualitasAirController;
use App\Http\Controllers\Admin\RekapPivotController;
use App\Http\Controllers\Admin\RumusDebitController;
use App\Http\Controllers\Admin\TelemetriPjtController;
use App\Http\Controllers\Admin\UnduhDataController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Portal publik belum dibuat; sementara halaman utama langsung ke panel admin (tamu diarahkan ke login).
Route::redirect('/', '/admin');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::controller(DugaAirController::class)->prefix('duga-air')->name('duga-air')->group(function () {
            Route::get('/', 'index');
            Route::get('tambah', 'create')->name('.create');
            Route::post('/', 'store')->name('.store');
            Route::get('{dugaAir}/ubah', 'edit')->name('.edit');
            Route::put('{dugaAir}', 'update')->name('.update');
            Route::delete('{dugaAir}', 'destroy')->name('.destroy');
        });

        Route::controller(DebitController::class)->prefix('debit')->name('debit')->group(function () {
            Route::get('/', 'index');
            Route::get('tambah', 'create')->name('.create');
            Route::post('/', 'store')->name('.store');
            Route::get('{debit}/ubah', 'edit')->name('.edit');
            Route::put('{debit}', 'update')->name('.update');
            Route::delete('{debit}', 'destroy')->name('.destroy');
        });

        Route::controller(KualitasAirController::class)->prefix('kualitas-air')->name('kualitas-air')->group(function () {
            Route::get('/', 'index');
            Route::get('tambah', 'create')->name('.create');
            Route::post('/', 'store')->name('.store');
            Route::get('{kualitasAir}/ubah', 'edit')->name('.edit');
            Route::put('{kualitasAir}', 'update')->name('.update');
            Route::delete('{kualitasAir}', 'destroy')->name('.destroy');
        });

        Route::controller(CurahHujanController::class)->prefix('curah-hujan')->name('curah-hujan')->group(function () {
            Route::get('/', 'index');
            Route::get('tambah', 'create')->name('.create');
            Route::post('/', 'store')->name('.store');
            Route::get('{curahHujan}/ubah', 'edit')->name('.edit');
            Route::put('{curahHujan}', 'update')->name('.update');
            Route::delete('{curahHujan}', 'destroy')->name('.destroy');
        });

        Route::controller(KlimatologiController::class)->prefix('klimatologi')->name('klimatologi')->group(function () {
            Route::get('/', 'index');
            Route::get('tambah', 'create')->name('.create');
            Route::post('/', 'store')->name('.store');
            Route::get('{klimatologi}/ubah', 'edit')->name('.edit');
            Route::put('{klimatologi}', 'update')->name('.update');
            Route::delete('{klimatologi}', 'destroy')->name('.destroy');
        });

        Route::controller(MukaAirTanahController::class)->prefix('muka-air-tanah')->name('muka-air-tanah')->group(function () {
            Route::get('/', 'index');
            Route::get('tambah', 'create')->name('.create');
            Route::post('/', 'store')->name('.store');
            Route::get('{mukaAirTanah}/ubah', 'edit')->name('.edit');
            Route::put('{mukaAirTanah}', 'update')->name('.update');
            Route::delete('{mukaAirTanah}', 'destroy')->name('.destroy');
        });

        // Data telemetri PJT baca-saja (asal: Awlrpjt.php, Arrpjt.php)
        foreach (array_keys(TelemetriPjtController::JENIS) as $jenis) {
            Route::get($jenis, TelemetriPjtController::class)->defaults('jenis', $jenis)->name($jenis);
        }

        // Curah hujan telemetri per jam dalam satu hari hujan (asal: Arrtele.php)
        Route::get('curah-hujan-telemetri', CurahHujanTelemetriController::class)->name('curah-hujan-telemetri');

        // Monitoring pengisian duga air manual (asal: Mawlr.php)
        Route::get('monitoring-duga-air', MonitoringDugaAirController::class)->name('monitoring-duga-air');

        // File arsip pos per tahun: unggah, hapus, generate PDF (asal: Fpos.php, Cetak.php)
        Route::controller(FileArsipController::class)->middleware('can:admin')->prefix('file-arsip')->name('arsip')->group(function () {
            Route::get('/', 'index');
            Route::get('berkas/{arsip}', 'berkas')->name('.berkas');
            Route::delete('berkas/{arsip}', 'destroy')->name('.destroy');
            Route::get('{pos}/{tahun}', 'show')->whereNumber('tahun')->name('.show');
            Route::post('{pos}/{tahun}/unggah', 'unggah')->whereNumber('tahun')->name('.unggah');
            Route::post('{pos}/{tahun}/generate', 'generate')->whereNumber('tahun')->name('.generate');
        });

        // Ketersediaan data: jumlah data per pos per bulan (asal: Sedia.php)
        foreach (array_keys(KetersediaanController::JENIS) as $jenis) {
            Route::get('ketersediaan/'.$jenis, KetersediaanController::class)->middleware('can:admin')->defaults('jenis', $jenis)->name('ketersediaan.'.$jenis);
        }

        // Konten portal: Publikasi dan Catatan Kejadian (asal: Galeri.php, Gpeta.php, Glosarium.php, Buletin.php, Album.php, Buku.php, Pengiriman.php, Kejadian.php)
        Route::get('lampiran/{lampiran}', [KontenController::class, 'lampiran'])->name('konten.lampiran');
        foreach (array_keys(KontenController::JENIS) as $jenis) {
            Route::controller(KontenController::class)->prefix($jenis)->name($jenis)->group(function () use ($jenis) {
                Route::get('/', 'index')->defaults('jenis', $jenis);
                Route::get('tambah', 'create')->defaults('jenis', $jenis)->name('.create');
                Route::post('/', 'store')->defaults('jenis', $jenis)->name('.store');
                Route::get('{id}/ubah', 'edit')->defaults('jenis', $jenis)->whereNumber('id')->name('.edit');
                Route::put('{id}', 'update')->defaults('jenis', $jenis)->whereNumber('id')->name('.update');
                Route::delete('{id}', 'destroy')->defaults('jenis', $jenis)->whereNumber('id')->name('.destroy');
            });
        }

        // Pengaturan: khusus superadmin
        Route::middleware('can:superadmin')->group(function () {
            // Ambang batas siaga per pos (asal: Batas.php)
            Route::get('ambang-batas', [AmbangBatasController::class, 'index'])->name('batas-siaga');
            Route::put('ambang-batas', [AmbangBatasController::class, 'update'])->name('batas-siaga.update');

            // Koefisien rumus debit per pos (asal: Rumus_debit.php)
            Route::get('rumus-debit', [RumusDebitController::class, 'index'])->name('rumus-debit');
            Route::put('rumus-debit', [RumusDebitController::class, 'update'])->name('rumus-debit.update');

            // Akun pengguna panel admin (asal: Pengguna.php)
            Route::controller(PenggunaController::class)->prefix('pengguna')->name('pengguna')->group(function () {
                Route::get('/', 'index');
                Route::get('tambah', 'create')->name('.create');
                Route::post('/', 'store')->name('.store');
                Route::get('{pengguna}/ubah', 'edit')->name('.edit');
                Route::put('{pengguna}', 'update')->name('.update');
                Route::delete('{pengguna}', 'destroy')->name('.destroy');
            });

            // Input data harian setahun dengan tempel dari Excel (asal: Input.php)
            foreach (array_keys(InputController::JENIS) as $jenis) {
                Route::controller(InputController::class)->prefix('input/'.$jenis)->name('input.'.$jenis)->group(function () use ($jenis) {
                    Route::get('/', 'create')->defaults('jenis', $jenis);
                    Route::post('pratinjau', 'pratinjau')->defaults('jenis', $jenis)->name('.pratinjau');
                    Route::post('/', 'store')->defaults('jenis', $jenis)->name('.store');
                });
            }

            // Pemetaan perangkat telemetri ke pos (asal: Postele.php)
            Route::controller(PerangkatTelemetriController::class)->prefix('perangkat-telemetri')->name('perangkat-telemetri')->group(function () {
                Route::get('/', 'index');
                Route::get('tambah', 'create')->name('.create');
                Route::post('/', 'store')->name('.store');
                Route::get('{perangkat}/ubah', 'edit')->name('.edit');
                Route::put('{perangkat}', 'update')->name('.update');
                Route::delete('{perangkat}', 'destroy')->name('.destroy');
            });
        });

        // Permintaan unduh data arsip pos (asal: Unduh.php)
        Route::controller(UnduhDataController::class)->middleware('can:admin')->prefix('unduh-data')->name('unduh.')->group(function () {
            Route::get('/', 'pengajuan')->name('pengajuan');
            Route::get('verifikasi', 'verifikasi')->name('verifikasi');
            Route::get('tambah', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('file/{token}/{arsip}', 'unduh')->name('file');
            Route::post('{permintaan}/{walidata}/proses', 'proses')->whereNumber('walidata')->name('proses');
            Route::get('{permintaan}/{walidata}', 'detail')->whereNumber('walidata')->name('detail');
            Route::delete('{permintaan}/{walidata}', 'destroy')->whereNumber('walidata')->name('destroy');
        });

        // Rekapitulasi berbentuk daftar baca-saja per bulan (asal: Rawlr.php, Rklimat.php)
        foreach (array_keys(RekapController::JENIS) as $jenis) {
            Route::get('rekap/'.$jenis, RekapController::class)->defaults('jenis', $jenis)->name('rekap.'.$jenis);
        }

        // Rekapitulasi berbentuk pivot setahun, kolom Jan s.d. Des (asal: Rarr.php, Rmat.php)
        foreach (array_keys(RekapPivotController::JENIS) as $jenis) {
            Route::get('rekap/'.$jenis, RekapPivotController::class)->defaults('jenis', $jenis)->name('rekap.'.$jenis);
        }

        // Rekapitulasi kualitas air: satu pos setahun, ditranspos (asal: Rkualitas_air.php)
        Route::get('rekap/kualitas-air', RekapKualitasAirController::class)->name('rekap.kualitas-air');
    });
});

<?php

use App\Http\Controllers\Admin\CurahHujanController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DebitController;
use App\Http\Controllers\Admin\DugaAirController;
use App\Http\Controllers\Admin\KlimatologiController;
use App\Http\Controllers\Admin\KualitasAirController;
use App\Http\Controllers\Admin\MukaAirTanahController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\RekapPivotController;
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

        // Rekapitulasi berbentuk daftar baca-saja per bulan (asal: Rawlr.php, Rklimat.php)
        foreach (array_keys(RekapController::JENIS) as $jenis) {
            Route::get('rekap/'.$jenis, RekapController::class)->defaults('jenis', $jenis)->name('rekap.'.$jenis);
        }

        // Rekapitulasi berbentuk pivot setahun, kolom Jan s.d. Des (asal: Rarr.php, Rmat.php)
        foreach (array_keys(RekapPivotController::JENIS) as $jenis) {
            Route::get('rekap/'.$jenis, RekapPivotController::class)->defaults('jenis', $jenis)->name('rekap.'.$jenis);
        }
    });
});

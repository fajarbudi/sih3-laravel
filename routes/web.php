<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DugaAirController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
    });
});

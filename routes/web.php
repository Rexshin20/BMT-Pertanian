<?php

use App\Http\Controllers\ImporController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanLabaRugiController;
use App\Http\Controllers\LaporanStokAkhirController;
use App\Http\Controllers\PembelianController;
use App\Http\Controllers\PemilikController;
use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\ProdukController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/edit', [AuthController::class, 'editAccount'])->name('account.edit');
    Route::put('/account', [AuthController::class, 'updateAccount'])->name('account.update');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/produk', [ProdukController::class, 'index'])->name('produk.index');
    Route::get('/produk/create', [ProdukController::class, 'create'])->name('produk.create');
    Route::post('/produk', [ProdukController::class, 'store'])->name('produk.store');
    Route::get('/produk/{produk}/edit', [ProdukController::class, 'edit'])->name('produk.edit');
    Route::put('/produk/{produk}', [ProdukController::class, 'update'])->name('produk.update');
    Route::delete('/produk/{produk}', [ProdukController::class, 'destroy'])->name('produk.destroy');
    Route::get('/pemilik', [PemilikController::class, 'index'])->name('pemilik.index');
    Route::post('/pemilik', [PemilikController::class, 'store'])->name('pemilik.store');
    Route::get('/pemilik/{pemilik}/edit', [PemilikController::class, 'edit'])->name('pemilik.edit');
    Route::put('/pemilik/{pemilik}', [PemilikController::class, 'update'])->name('pemilik.update');
    Route::delete('/pemilik/{pemilik}', [PemilikController::class, 'destroy'])->name('pemilik.destroy');
    Route::get('/impor', [ImporController::class, 'index'])->name('impor.index');
    Route::post('/impor/preview', [ImporController::class, 'preview'])->name('impor.preview');
    Route::post('/impor/simpan', [ImporController::class, 'simpan'])->name('impor.simpan');
    Route::get('/pembelian', [PembelianController::class, 'index'])->name('pembelian.index');
    Route::get('/pembelian/create', [PembelianController::class, 'create'])->name('pembelian.create');
    Route::get('/pembelian/master-data', [PembelianController::class, 'index'])->name('pembelian.master-data');
    Route::post('/pembelian', [PembelianController::class, 'store'])->name('pembelian.store');
    Route::get('/pembelian/template', [PembelianController::class, 'template'])->name('pembelian.template');
    Route::post('/pembelian/import/preview', [PembelianController::class, 'importPreview'])->middleware('throttle:20,1')->name('pembelian.import.preview');
    Route::post('/pembelian/import', [PembelianController::class, 'importStore'])->name('pembelian.import.store');
    Route::get('/penjualan', [PenjualanController::class, 'index'])->name('penjualan.index');
    Route::get('/penjualan/create', [PenjualanController::class, 'create'])->name('penjualan.create');
    Route::get('/penjualan/master-data', [PenjualanController::class, 'index'])->name('penjualan.master-data');
    Route::post('/penjualan', [PenjualanController::class, 'store'])->name('penjualan.store');
    Route::get('/laporan/laba-rugi', [LaporanLabaRugiController::class, 'index'])->name('laporan.laba-rugi');
    Route::get('/laporan/stok-akhir', [LaporanStokAkhirController::class, 'index'])->name('laporan.stok-akhir');
});

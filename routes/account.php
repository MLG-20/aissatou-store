<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('compte')->name('account.')->group(function () {
    Route::get('/adresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/adresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('/adresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/adresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

    Route::get('/commandes', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
});

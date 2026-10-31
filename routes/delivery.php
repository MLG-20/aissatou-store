<?php

use App\Http\Controllers\Delivery\CourierOrderController;
use App\Http\Controllers\Delivery\CourierTourController;
use Illuminate\Support\Facades\Route;

/*
| Pages du livreur, sans compte. Lien d'une commande : signé, contrôlé dans le
| contrôleur pour afficher une page claire si le lien est mort ; GET et POST
| partagent la même adresse signée. « Ma tournée » : lien personnel révocable.
*/
Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('livraison/{order:number}', [CourierOrderController::class, 'show'])->name('courier.order');
    Route::post('livraison/{order:number}', [CourierOrderController::class, 'update'])->name('courier.order.update');

    Route::get('tournee/{token}', [CourierTourController::class, 'show'])->name('courier.tour');
    Route::post('tournee/{token}/{order:number}', [CourierTourController::class, 'update'])->name('courier.tour.update');
});

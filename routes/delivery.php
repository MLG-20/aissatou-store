<?php

use App\Http\Controllers\Delivery\CourierOrderController;
use Illuminate\Support\Facades\Route;

/*
| Pages du livreur, sans compte : le lien signé reçu sur WhatsApp fait foi
| (contrôlé dans le contrôleur, pour afficher une page claire si le lien est
| mort). GET et POST partagent la même adresse signée.
*/
Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('livraison/{order:number}', [CourierOrderController::class, 'show'])->name('courier.order');
    Route::post('livraison/{order:number}', [CourierOrderController::class, 'update'])->name('courier.order.update');
});

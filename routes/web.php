<?php

use App\Enums\Order\PaymentEventType;
use App\Filament\Resources\Order\PaymentEventResource;
use App\Filament\Resources\Order\PaymentResource;
use App\Http\Controllers\Admin\OrderInvoiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\HomeController;
use App\Models\Order\PaymentEvent;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/dashboard', function () {
    return view('account.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/shop.php';
require __DIR__.'/account.php';
require __DIR__.'/auth.php';

// Facture PDF : guard `admin` uniquement — un client ne doit jamais y accéder,
// même en devinant l'URL (données sensibles d'un autre client).
Route::middleware('auth:admin')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('commandes/{order}/facture', OrderInvoiceController::class)->name('orders.invoice');

    // Ancien « Journal des paiements » (liens et favoris d'avant la refonte) :
    // chaque événement renvoie vers la fiche de son paiement, ou vers celle du
    // message suspect. Les pages cibles contrôlent la permission Paiements.
    Route::get('order/payment-events', fn () => redirect(PaymentResource::getUrl('index', panel: 'admin')));
    Route::get('order/payment-events/{event}', fn (PaymentEvent $event) => redirect(
        $event->type === PaymentEventType::SuspiciousNotification || ! $event->payment_id
            ? PaymentEventResource::getUrl('view', ['record' => $event], panel: 'admin')
            : PaymentResource::getUrl('view', ['record' => $event->payment_id], panel: 'admin')
    ));

});

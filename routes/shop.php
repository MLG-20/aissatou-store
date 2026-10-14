<?php

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CollectionController;
use App\Http\Controllers\Shop\ContactController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ReviewController;
use App\Models\Content\SiteSetting;
use Illuminate\Support\Facades\Route;

Route::get('catalogue', [ProductController::class, 'index'])->name('shop.index');
Route::get('categories/{category:slug}', [CategoryController::class, 'show'])->name('shop.category');
Route::get('produits/{product:slug}', [ProductController::class, 'show'])->name('shop.product');

// Sections présentes dans la maquette dont le module arrive en Phase 2.2
// (Journal, Liste d'envies). Elles ont une page d'attente plutôt qu'un lien
// mort ; route et vue disparaissent quand le module correspondant est livré
// (comme Collections en 2.2B).
Route::get('collections', [CollectionController::class, 'index'])->name('shop.collections');
Route::get('collections/{collection:slug}', [CollectionController::class, 'show'])->name('shop.collection');

Route::get('journal', fn () => view('shop.coming-soon', [
    'heading' => 'Journal',
    'intro' => 'Nos histoires de matières, de gestes et de sélections.',
]))->name('shop.journal');

Route::get('liste-envies', fn () => view('shop.coming-soon', [
    'heading' => "Liste d'envies",
    'intro' => 'Retrouvez ici les pièces que vous mettez de côté.',
]))->name('shop.wishlist');

Route::get('a-propos', fn () => view('shop.about', ['settings' => SiteSetting::current()]))->name('shop.about');
Route::get('contact', [ContactController::class, 'show'])->name('shop.contact');
Route::post('contact', [ContactController::class, 'store'])->name('shop.contact.store');

Route::post('avis', [ReviewController::class, 'store'])->name('shop.reviews.store');

Route::get('panier', [CartController::class, 'index'])->name('shop.cart');
Route::post('panier', [CartController::class, 'store'])->name('shop.cart.store');
Route::patch('panier/{item}', [CartController::class, 'update'])->name('shop.cart.update');
Route::delete('panier/{item}', [CartController::class, 'destroy'])->name('shop.cart.destroy');

// Tunnel de commande (compte requis : il s'appuie sur le carnet d'adresses).
Route::middleware('auth')->group(function (): void {
    Route::get('commande', [CheckoutController::class, 'show'])->name('shop.checkout');
    Route::post('commande', [CheckoutController::class, 'store'])->name('shop.checkout.store');
    Route::get('commande/merci/{order:number}', [CheckoutController::class, 'success'])->name('shop.checkout.success');
});

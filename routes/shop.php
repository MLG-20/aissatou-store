<?php

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CategoryController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CollectionController;
use App\Http\Controllers\Shop\ContactController;
use App\Http\Controllers\Shop\JournalController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\WishlistController;
use App\Models\Content\SiteSetting;
use Illuminate\Support\Facades\Route;

Route::get('catalogue', [ProductController::class, 'index'])->name('shop.index');
Route::get('categories/{category:slug}', [CategoryController::class, 'show'])->name('shop.category');
Route::get('produits/{product:slug}', [ProductController::class, 'show'])->name('shop.product');

// Toutes les sections de la maquette sont livrées (Collections 2.2B,
// Liste d'envies 2.2C, Journal 2.2D) : plus aucune page d'attente.
Route::get('collections', [CollectionController::class, 'index'])->name('shop.collections');
Route::get('collections/{collection:slug}', [CollectionController::class, 'show'])->name('shop.collection');

Route::get('journal', [JournalController::class, 'index'])->name('shop.journal');
Route::get('journal/{post:slug}', [JournalController::class, 'show'])->name('shop.post');

Route::get('liste-envies', [WishlistController::class, 'index'])->name('shop.wishlist');
Route::post('liste-envies/{product}', [WishlistController::class, 'toggle'])->name('shop.wishlist.toggle');
Route::delete('liste-envies/{product}', [WishlistController::class, 'destroy'])->name('shop.wishlist.destroy');

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

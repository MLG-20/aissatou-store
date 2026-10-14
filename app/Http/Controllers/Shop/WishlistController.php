<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\WishlistException;
use App\Http\Controllers\Controller;
use App\Models\Catalogue\Product;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlistService) {}

    public function index(Request $request): View
    {
        $wishlist = $this->wishlistService->currentWishlist($request)
            ->load(['items.product.images', 'items.product.category', 'items.product.variants']);

        // Un produit désactivé ou supprimé après sa mise de côté disparaît de
        // la liste (même logique anti-énumération que le catalogue).
        $items = $wishlist->items->filter(
            fn ($item) => $item->product && $item->product->is_active
        )->values();

        return view('shop.wishlist', ['items' => $items]);
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $wishlist = $this->wishlistService->currentWishlist($request);

        try {
            $added = $this->wishlistService->toggle($wishlist, $product);
        } catch (WishlistException $exception) {
            return back()->withErrors(['wishlist' => $exception->getMessage()]);
        }

        return back()->with('status', $added ? 'Ajouté à votre liste d\'envies.' : 'Retiré de votre liste d\'envies.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $wishlist = $this->wishlistService->currentWishlist($request);
        $wishlist->items()->where('product_id', $product->id)->delete();

        return back()->with('status', 'Retiré de votre liste d\'envies.');
    }
}

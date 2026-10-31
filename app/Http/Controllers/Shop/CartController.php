<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddCartItemRequest;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    public function index(Request $request): View
    {
        $cart = $this->cartService->currentCart($request)
            ->load(['items.product.images', 'items.variant']);

        return view('shop.cart', ['cart' => $cart]);
    }

    public function store(AddCartItemRequest $request): RedirectResponse
    {
        $product = Product::findOrFail($request->integer('product_id'));
        $variant = $request->filled('variant_id')
            ? ProductVariant::findOrFail($request->integer('variant_id'))
            : null;

        $cart = $this->cartService->currentCart($request);

        $buyNow = $request->boolean('buy_now');

        try {
            $this->cartService->addItem($cart, $product, $variant, $request->integer('quantity'), replace: $buyNow);
        } catch (CartException $exception) {
            return back()->withErrors(['variant_id' => $exception->getMessage()]);
        }

        // « Acheter maintenant » : droit à la commande. Une visiteuse passe
        // par la connexion, qui l'y ramène avec son panier fusionné.
        if ($buyNow) {
            return redirect()->route('shop.checkout');
        }

        // On reste sur la page courante et on ouvre le tiroir panier (flash lu
        // par le layout), au lieu d'envoyer sur la page /panier — parcours plus
        // fluide et « premium ».
        return back()->with('cart_opened', true);
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItemOwnership($request, $item);

        $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:50']]);

        try {
            $this->cartService->updateQuantity($item, $request->integer('quantity'));
        } catch (CartException $exception) {
            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return redirect()->route('shop.cart');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItemOwnership($request, $item);

        $this->cartService->removeItem($item);

        // Retour à la page courante avec réouverture du tiroir (retrait depuis
        // le tiroir ou la page panier), pour garder le contexte.
        return back()->with('cart_opened', true);
    }

    /**
     * L'id d'une ligne de panier est un entier séquentiel devinable — sans ce
     * contrôle, n'importe qui pourrait modifier ou supprimer la ligne de
     * panier d'un autre visiteur via l'URL.
     */
    private function authorizeItemOwnership(Request $request, CartItem $item): void
    {
        $cart = $this->cartService->currentCart($request);

        abort_unless($item->cart_id === $cart->id, 403);
    }
}

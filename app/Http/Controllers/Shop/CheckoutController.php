<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutStoreRequest;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly PaymentManager $payments,
    ) {}

    /**
     * Récapitulatif avant validation : lignes, total recalculé, carnet
     * d'adresses, moyens de paiement disponibles.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->cartService->currentCart($request)
            ->load(['items.product.images', 'items.variant']);

        if ($cart->items->isEmpty()) {
            return redirect()->route('shop.cart')->withErrors(['cart' => 'Votre panier est vide.']);
        }

        $addresses = $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get();

        return view('shop.checkout', [
            'cart' => $cart,
            'addresses' => $addresses,
            'providers' => $this->payments->available(),
        ]);
    }

    public function store(CheckoutStoreRequest $request): RedirectResponse
    {
        $cart = $this->cartService->currentCart($request);
        $address = Address::findOrFail($request->integer('address_id'));

        try {
            $order = $this->orderService->createFromCart(
                $request->user(), $cart, $address, $request->string('provider')->toString()
            );
        } catch (OrderException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        return redirect()->route('shop.checkout.success', $order->number);
    }

    /**
     * Page de confirmation post-commande avec le numéro de commande.
     * Anti-IDOR : le numéro n'est pas séquentiel mais la vérification
     * d'appartenance reste obligatoire (même pattern que les adresses).
     */
    public function success(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load(['items', 'payment']);

        return view('shop.order-confirmation', ['order' => $order]);
    }
}

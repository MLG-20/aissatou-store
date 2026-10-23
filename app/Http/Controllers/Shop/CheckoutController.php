<?php

namespace App\Http\Controllers\Shop;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Exceptions\OrderException;
use App\Exceptions\PromoCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutStoreRequest;
use App\Models\Customer\Address;
use App\Models\Delivery\DeliveryZone;
use App\Models\Order\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentService;
use App\Services\PromoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const PROMO_SESSION_KEY = 'checkout.promo_code';

    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly PaymentManager $payments,
        private readonly PaymentService $paymentService,
        private readonly PromoService $promos,
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

        // Frais calculés serveur pour chaque adresse du carnet : la vue ne
        // fait qu'afficher ces chiffres (Alpine), le total validé est
        // recalculé côté serveur dans OrderService — jamais depuis le client.
        $fees = $addresses->mapWithKeys(function (Address $address): array {
            $zone = DeliveryZone::forCity($address->city);

            return [$address->id => [
                'fee' => $zone ? (float) $zone->fee : 0,
                'label' => $zone
                    ? $zone->name.($zone->delay_label ? ' — '.$zone->delay_label : '')
                    : 'Retrait ou livraison à convenir',
            ]];
        })->all();

        // Code promo mémorisé : réévalué à chaque affichage sur le panier
        // actuel (aperçu seulement — la décision se prend à la validation).
        $promo = null;

        if ($code = $request->session()->get(self::PROMO_SESSION_KEY)) {
            try {
                $promo = $this->promos->quote($code, $request->user(), PromoService::linesFromCart($cart));
            } catch (PromoCodeException $exception) {
                $request->session()->forget(self::PROMO_SESSION_KEY);
                session()->now('promo_error', $exception->getMessage());
            }
        }

        return view('shop.checkout', [
            'cart' => $cart,
            'addresses' => $addresses,
            'fees' => $fees,
            'providers' => $this->payments->available(),
            'promo' => $promo,
        ]);
    }

    public function store(CheckoutStoreRequest $request): RedirectResponse
    {
        $cart = $this->cartService->currentCart($request);
        $address = Address::findOrFail($request->integer('address_id'));

        try {
            $order = $this->orderService->createFromCart(
                $request->user(),
                $cart,
                $address,
                $request->string('provider')->toString(),
                $request->session()->get(self::PROMO_SESSION_KEY),
            );
        } catch (PromoCodeException $exception) {
            // Code devenu invalide entre la saisie et la validation : rien
            // n'est créé, le code est retiré, le client voit pourquoi.
            $request->session()->forget(self::PROMO_SESSION_KEY);

            return back()->withErrors(['promo_code' => $exception->getMessage()]);
        } catch (OrderException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        $request->session()->forget(self::PROMO_SESSION_KEY);

        return $this->startPayment($order);
    }

    /** Saisie d'un code promo : aperçu de la remise, mémorisé en session. */
    public function applyPromo(Request $request): RedirectResponse
    {
        $data = $request->validate(['promo_code' => ['required', 'string', 'max:40']]);
        $cart = $this->cartService->currentCart($request);

        try {
            $quote = $this->promos->quote($data['promo_code'], $request->user(), PromoService::linesFromCart($cart));
        } catch (PromoCodeException $exception) {
            $request->session()->forget(self::PROMO_SESSION_KEY);

            return back()->withErrors(['promo_code' => $exception->getMessage()])->withInput();
        }

        $request->session()->put(self::PROMO_SESSION_KEY, $quote->code->code);

        return back()->with('promo_status', 'Code '.$quote->code->code.' appliqué.');
    }

    public function removePromo(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PROMO_SESSION_KEY);

        return back();
    }

    /**
     * Retour de PayTech (succès comme annulation) puis page d'attente qui se
     * rafraîchit. Le statut affiché vient TOUJOURS du serveur : IPN signé ou
     * vérification active auprès de PayTech — un `?status=success` ajouté à
     * la main ne change rien.
     */
    public function payment(Request $request, Order $order): View|RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $payment = $order->payment;

        if (! $this->payments->find($payment->provider)?->isOnline()) {
            return redirect()->route('shop.checkout.success', $order->number);
        }

        if ($payment->status === PaymentStatus::Processing) {
            $this->paymentService->reconcile($payment, $request->ip());
            $payment->refresh();
        }

        if ($payment->status === PaymentStatus::Confirmed) {
            return redirect()->route('shop.checkout.success', $order->number);
        }

        return view('shop.checkout-payment', [
            'order' => $order->fresh(),
            'payment' => $payment,
            'canRetry' => $this->canRetry($order),
        ]);
    }

    /** Nouvelle tentative après échec ou abandon, tant que le délai court. */
    public function retry(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        // Un paiement de la tentative précédente a pu aboutir entre-temps :
        // on vérifie avant de proposer de payer une seconde fois.
        if ($order->payment->status === PaymentStatus::Processing) {
            $this->paymentService->reconcile($order->payment, $request->ip());
        }

        if ($order->payment->fresh()->status === PaymentStatus::Confirmed) {
            return redirect()->route('shop.checkout.success', $order->number);
        }

        abort_unless($this->canRetry($order), 409);

        return $this->startPayment($order->fresh());
    }

    /**
     * Après commit de la commande : envoi chez le fournisseur en ligne, ou
     * confirmation directe (paiement à la livraison).
     */
    private function startPayment(Order $order): RedirectResponse
    {
        $payment = $order->payment;
        $provider = $this->payments->find($payment->provider);

        try {
            $url = $provider?->checkout($payment);
        } catch (PaymentException $exception) {
            return redirect()->route('shop.checkout.payment', $order->number)
                ->withErrors(['payment' => $exception->getMessage()]);
        }

        return $url
            ? redirect()->away($url)
            : redirect()->route('shop.checkout.success', $order->number);
    }

    private function canRetry(Order $order): bool
    {
        return $order->status === OrderStatus::New
            && $order->payment->status !== PaymentStatus::Confirmed
            && $order->payment->expires_at?->isFuture() === true;
    }

    /**
     * Page de confirmation post-commande avec le numéro de commande.
     * Anti-IDOR : le numéro n'est pas séquentiel mais la vérification
     * d'appartenance reste obligatoire (même pattern que les adresses).
     */
    public function success(Request $request, Order $order): View|RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load(['items', 'payment']);

        // Pas de « Merci » pour un paiement en ligne non confirmé, même en
        // tapant l'URL : retour à la page d'attente.
        $online = $this->payments->find($order->payment->provider)?->isOnline();

        if ($online && $order->payment->status !== PaymentStatus::Confirmed) {
            return redirect()->route('shop.checkout.payment', $order->number);
        }

        return view('shop.order-confirmation', ['order' => $order, 'paidOnline' => (bool) $online]);
    }
}

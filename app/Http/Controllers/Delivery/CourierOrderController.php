<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Delivery\Concerns\AppliesCourierAction;
use App\Models\Delivery\Courier;
use App\Models\Order\Order;
use App\Services\Delivery\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Page d'une commande pour le livreur, ouverte par le lien signé reçu sur
 * WhatsApp : pas de compte, la signature et le livreur assigné font foi.
 */
class CourierOrderController extends Controller
{
    use AppliesCourierAction;

    public function __construct(private readonly CourierService $couriers) {}

    public function show(Request $request, Order $order): View|Response
    {
        if (! $courier = $this->authorizedCourier($request, $order)) {
            return response()->view('courier.link-expired', status: 403);
        }

        return view('courier.order', [
            'order' => $order->load(['items', 'payment']),
            'courier' => $courier,
            'actionUrl' => $request->getRequestUri(),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse|Response
    {
        if (! $courier = $this->authorizedCourier($request, $order)) {
            return response()->view('courier.link-expired', status: 403);
        }

        // Retour sur la même adresse signée, en GET.
        return $this->applyCourierAction($request, $order, $courier, $request->getRequestUri());
    }

    /**
     * Signature relative valide et non expirée, livreur actif et toujours
     * assigné à cette commande ; sinon le lien est mort.
     */
    private function authorizedCourier(Request $request, Order $order): ?Courier
    {
        if (! $request->hasValidRelativeSignature()) {
            return null;
        }

        $courier = Courier::find($request->integer('courier'));

        return $this->couriers->canHandle($courier, $order) ? $courier : null;
    }
}

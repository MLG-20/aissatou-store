<?php

namespace App\Http\Controllers\Delivery\Concerns;

use App\Exceptions\OrderException;
use App\Models\Delivery\Courier;
use App\Models\Order\Order;
use App\Services\Delivery\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Appui du livreur (« je pars », « livrée », « client absent »), depuis la page d'une commande
 * ou depuis « Ma tournée ». L'accès a déjà été vérifié par l'appelant.
 */
trait AppliesCourierAction
{
    protected function applyCourierAction(Request $request, Order $order, Courier $courier, string $returnTo): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'in:depart,livree,absent']])['action'];
        $couriers = app(CourierService::class);

        try {
            match ($action) {
                'depart' => $couriers->pickUp($order, $courier),
                'livree' => $couriers->deliver($order, $courier),
                'absent' => $couriers->reportAbsent($order, $courier),
            };
        } catch (OrderException) {
            // Double appui, ou statut changé entre-temps par la boutique.
            return redirect($returnTo)->with('courier_error', 'Cette commande a déjà changé de statut. La page est à jour.');
        }

        return redirect($returnTo)->with('courier_status', $order->number.' : '.match ($action) {
            'depart' => 'c\'est noté, la cliente est prévenue que sa commande est en route.',
            'livree' => 'livraison enregistrée. Merci !',
            'absent' => 'c\'est noté, la boutique va recontacter la cliente. Gardez le colis.',
        });
    }
}

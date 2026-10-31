<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Delivery\Concerns\AppliesCourierAction;
use App\Models\Order\Order;
use App\Services\Delivery\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * « Ma tournée » : lien personnel du livreur, sans date limite mais
 * révocable depuis l'admin. Liste toutes ses commandes à préparer ou en route.
 */
class CourierTourController extends Controller
{
    use AppliesCourierAction;

    public function __construct(private readonly CourierService $couriers) {}

    public function show(string $token): View|Response
    {
        if (! $courier = $this->couriers->courierForTour($token)) {
            return response()->view('courier.link-expired', status: 403);
        }

        return view('courier.tour', [
            'courier' => $courier,
            'token' => $token,
            // Les commandes en route d'abord, puis les plus anciennes.
            'orders' => $courier->ordersInProgress()
                ->with(['items', 'payment'])
                ->orderByRaw('status = ? desc', [OrderStatus::Shipped->value])
                ->oldest()
                ->get(),
            'deliveredToday' => $courier->orders()
                ->where('status', OrderStatus::Delivered)
                ->whereDate('updated_at', today())
                ->count(),
        ]);
    }

    public function update(Request $request, string $token, Order $order): RedirectResponse|Response
    {
        $courier = $this->couriers->courierForTour($token);

        if (! $this->couriers->canHandle($courier, $order)) {
            return response()->view('courier.link-expired', status: 403);
        }

        return $this->applyCourierAction($request, $order, $courier, route('courier.tour', $token, absolute: false));
    }
}

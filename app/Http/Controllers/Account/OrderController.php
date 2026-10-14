<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Historique des commandes du compte : liste paginée, la plus récente
     * d'abord. Seules les commandes du compte connecté (requête scopée).
     */
    public function index(Request $request): View
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('account.orders', ['orders' => $orders]);
    }

    /**
     * Détail d'une commande. Anti-IDOR : même pattern que les adresses —
     * l'id étant devinable, l'appartenance est vérifiée systématiquement.
     */
    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load(['items', 'payment', 'history']);

        return view('account.order-show', ['order' => $order]);
    }
}

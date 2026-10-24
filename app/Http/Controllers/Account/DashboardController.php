<?php

namespace App\Http\Controllers\Account;

use App\Enums\Order\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Catalogue\Product;
use App\Services\WishlistService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tableau de bord de l'espace cliente : ce qui la concerne, elle, maintenant —
 * sa commande en cours (ou la dernière), ses coups de cœur, son adresse par
 * défaut, son panier, et ce qui manque à son profil.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, WishlistService $wishlists): View
    {
        $user = $request->user();

        $orderRelations = ['items.product.images', 'history'];

        $activeOrder = $user->orders()
            ->whereIn('status', [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Shipped])
            ->with($orderRelations)
            ->latest()
            ->first();

        $lastOrder = $activeOrder ? null : $user->orders()->with($orderRelations)->latest()->first();

        $wishlistIds = $wishlists->currentProductIds($request);
        $favorites = Product::query()
            ->active()
            ->whereIn('id', $wishlistIds)
            ->with(['category', 'images', 'variants' => fn ($query) => $query->active()])
            ->latest()
            ->take(4)
            ->get();

        $defaultAddress = $user->addresses()->orderByDesc('is_default')->latest()->first();
        $cartCount = (int) $user->cart?->items()->sum('quantity');
        $ordersCount = $user->orders()->count();

        // Profil : quatre jalons simples, chacun avec son lien pour le compléter.
        $profileSteps = [
            ['label' => 'Compte créé', 'done' => true, 'route' => null],
            ['label' => 'Téléphone ajouté', 'done' => filled($user->phone), 'route' => 'profile.edit'],
            ['label' => 'Adresse enregistrée', 'done' => $defaultAddress !== null, 'route' => 'account.addresses.index'],
            ['label' => 'Première commande', 'done' => $ordersCount > 0, 'route' => 'shop.index'],
        ];

        return view('account.dashboard', [
            'activeOrder' => $activeOrder,
            'lastOrder' => $lastOrder,
            'favorites' => $favorites,
            'favoritesCount' => count($wishlistIds),
            'defaultAddress' => $defaultAddress,
            'cartCount' => $cartCount,
            'profileSteps' => $profileSteps,
            'profileCompletion' => (int) round(collect($profileSteps)->where('done', true)->count() / count($profileSteps) * 100),
        ]);
    }
}

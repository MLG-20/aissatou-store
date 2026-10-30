<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\FilterProductsRequest;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Material;
use App\Models\Catalogue\Product;
use App\Models\Content\SiteSetting;
use App\Models\Delivery\DeliveryZone;
use App\Services\Payment\PaymentManager;
use App\Services\WishlistService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const RECENTLY_VIEWED = 'recently_viewed_products';

    public function index(FilterProductsRequest $request): View
    {
        $products = Product::query()
            ->active()
            ->filter($request->filters())
            ->with(['category', 'material', 'images', 'variants' => fn ($query) => $query->active()])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Une requête envoyée par le composant Alpine de recherche/filtres (voir
        // resources/js/shop-filters.js) ne reçoit que la grille de produits, pas
        // toute la page — évite un rechargement complet à chaque filtre.
        if ($request->ajax()) {
            return view('shop.partials.product-grid', ['products' => $products]);
        }

        return view('shop.index', [
            'products' => $products,
            'categories' => Category::query()->active()->orderBy('position')->get(),
            'materials' => Material::query()->active()->orderBy('position')->get(),
            'collections' => Collection::query()->active()->orderBy('position')->get(),
            // L'onglet « Nouveautés » de la maquette mène ici, filtré. Sans ce
            // drapeau, la page s'annoncerait « Tout le catalogue » alors qu'elle
            // n'en montre qu'une partie — c'est exactement ce qui donnait
            // l'impression que l'onglet ne servait à rien.
            'showingNewArrivals' => $request->boolean('nouveautes'),
        ]);
    }

    public function show(Request $request, Product $product, WishlistService $wishlistService, PaymentManager $payments): View
    {
        // Les produits soft-supprimés sont déjà exclus par le scope par défaut ;
        // les produits inactifs doivent aussi renvoyer 404, pour empêcher
        // l'énumération d'articles désactivés via leur slug.
        abort_unless($product->is_active, 404);

        $product->load(['category', 'material', 'images', 'variants' => fn ($query) => $query->active()]);

        // « Vus récemment » : identifiants en session, du plus récent au plus
        // ancien, lus AVANT d'y ajouter la pièce affichée (elle n'a pas à se
        // recommander elle-même).
        $recentIds = array_values(array_diff($request->session()->get(self::RECENTLY_VIEWED, []), [$product->id]));
        $request->session()->put(self::RECENTLY_VIEWED, array_slice([$product->id, ...$recentIds], 0, 9));

        $listing = fn () => Product::query()->active()->with(['category', 'images', 'variants' => fn ($query) => $query->active()]);

        // « Vous aimerez aussi » : même univers.
        $related = $listing()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->latest()
            ->take(4)
            ->get();

        // « Complétez votre tenue » : une pièce par AUTRE univers (un col avec
        // un hijab, un parfum avec un foulard…), pour élargir le panier.
        $completeTheLook = $listing()
            ->where('category_id', '!=', $product->category_id)
            ->latest()
            ->take(40)
            ->get()
            ->filter->isInStock()
            ->unique('category_id')
            ->take(4)
            ->values();

        $recentlyViewed = $listing()
            ->whereKey(array_slice($recentIds, 0, 8))
            ->get()
            ->sortBy(fn (Product $item) => array_search($item->id, $recentIds, true))
            ->values();

        return view('shop.product', [
            'product' => $product,
            'inWishlist' => in_array($product->id, $wishlistService->currentProductIds($request), true),
            'related' => $related,
            'completeTheLook' => $completeTheLook,
            'recentlyViewed' => $recentlyViewed,
            'deliveryZones' => DeliveryZone::activeOrdered(),
            'paymentMethods' => collect($payments->available())->map->label(),
            'cashOnDelivery' => collect($payments->available())->contains(fn ($provider) => ! $provider->isOnline()),
            'whatsappAvailable' => filled(SiteSetting::current()->contact_whatsapp),
        ]);
    }
}

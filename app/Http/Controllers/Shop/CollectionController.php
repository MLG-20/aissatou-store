<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\FilterProductsRequest;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Material;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(): View
    {
        return view('shop.collections', [
            'collections' => Collection::query()->active()->withCount('products')->orderBy('position')->get(),
        ]);
    }

    public function show(FilterProductsRequest $request, Collection $collection): View
    {
        // Même règle anti-énumération que catégories et produits.
        abort_unless($collection->is_active, 404);

        // La page est déjà scopée à cette collection : on ignore un éventuel
        // collection_id transmis (même pattern que la page catégorie).
        $filters = [...$request->filters(), 'collection_id' => $collection->id];

        $products = $collection->products()
            ->active()
            ->filter($filters)
            ->with(['category', 'material', 'images', 'variants' => fn ($query) => $query->active()])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        if ($request->ajax()) {
            return view('shop.partials.product-grid', ['products' => $products]);
        }

        return view('shop.collection', [
            'collection' => $collection,
            'products' => $products,
            'materials' => Material::query()->active()->orderBy('position')->get(),
        ]);
    }
}

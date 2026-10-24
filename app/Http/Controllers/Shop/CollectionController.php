<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\FilterProductsRequest;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(): View
    {
        return view('shop.collections', [
            // Chaque chapitre montre quelques-unes de ses vraies pièces : on ne
            // compte et n'affiche que celles en vente.
            'collections' => $this->activeCollections()
                ->with(['products' => fn ($query) => $query->active()->with('images')->latest()->limit(4)])
                ->get(),
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

        // Numéro de chapitre (même ordre que la page Collections) et les autres
        // collections, pour « Poursuivre la lecture ».
        $all = $this->activeCollections()->get();
        $collection->loadCount(['products' => fn ($query) => $query->active()]);

        return view('shop.collection', [
            'collection' => $collection,
            'products' => $products,
            'materials' => Material::query()->active()->orderBy('position')->get(),
            'chapter' => $all->search(fn (Collection $item) => $item->is($collection)) + 1,
            'others' => $all->reject(fn (Collection $item) => $item->is($collection))->take(3)->values(),
        ]);
    }

    /** Collections en ligne, dans l'ordre éditorial, avec leur nombre de pièces en vente. */
    private function activeCollections(): Builder
    {
        return Collection::query()
            ->active()
            ->withCount(['products' => fn ($query) => $query->active()])
            ->orderBy('position');
    }
}

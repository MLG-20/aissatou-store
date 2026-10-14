<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\FilterProductsRequest;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Material;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(FilterProductsRequest $request, Category $category): View
    {
        // Même règle anti-énumération que pour les produits : une catégorie désactivée renvoie 404.
        abort_unless($category->is_active, 404);

        $category->load(['children' => fn ($query) => $query->active()->orderBy('position')]);

        // category_id est déjà fixé par la route (on est dans cette catégorie) :
        // on ignore un éventuel category_id transmis dans la requête pour ne pas
        // permettre de sortir du périmètre de la page via un paramètre d'URL.
        $filters = [...$request->filters(), 'category_id' => null];

        $products = $category->products()
            ->active()
            ->filter($filters)
            ->with(['category', 'material', 'images', 'variants' => fn ($query) => $query->active()])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        if ($request->ajax()) {
            return view('shop.partials.product-grid', ['products' => $products]);
        }

        return view('shop.category', [
            'category' => $category,
            'products' => $products,
            'materials' => Material::query()->active()->orderBy('position')->get(),
            'collections' => Collection::query()->active()->orderBy('position')->get(),
        ]);
    }
}

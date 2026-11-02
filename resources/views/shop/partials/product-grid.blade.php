@if ($products->isEmpty())
    <p class="text-brand-muted">Aucun produit ne correspond à ces critères.</p>
@else
    <h2 class="sr-only">Produits</h2>
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-10">
        @foreach ($products as $product)
            <x-shop.product-card :product="$product" />
        @endforeach
    </div>

    {{-- data-pagination : seuls ces liens sont interceptés en fetch par
         shop-filters.js. Les liens des cartes produit doivent, eux, naviguer
         normalement (sinon la fiche produit entière serait injectée dans la
         grille — header + footer en double). --}}
    <div class="mt-10" data-pagination>
        {{ $products->links() }}
    </div>
@endif

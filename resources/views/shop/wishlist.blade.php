{{-- Liste d'envies : connectée, elle vit dans l'espace cliente (même cadre que
     les autres pages du compte) ; visiteuse, c'est une page de la boutique. --}}
@auth
    <x-account-layout title="Mes coups de cœur">
        <div class="mb-6 flex items-end justify-between gap-4">
            <div>
                <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Rien qu'à vous</span>
                <h2 class="mt-2 font-display text-3xl text-brand-ink">Mes coups de cœur</h2>
            </div>
            @if ($items->isNotEmpty())
                <p class="text-sm text-brand-muted">{{ $items->count() }} {{ $items->count() > 1 ? 'pièces mises de côté' : 'pièce mise de côté' }}</p>
            @endif
        </div>

        @include('shop.partials.wishlist-items')
    </x-account-layout>
@else
    <x-shop-layout :title="'Ma liste d\'envies — '.config('app.name')">
        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8">
            <p class="text-xs uppercase tracking-[0.25em] text-brand-signature">Mis de côté</p>
            <h1 class="mb-8 mt-2 font-display text-3xl">Ma liste d'envies</h1>

            @include('shop.partials.wishlist-items')
        </div>
    </x-shop-layout>
@endauth

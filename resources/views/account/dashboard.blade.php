<x-account-layout title="Mon espace">
    @php
        $featuredOrder = $activeOrder ?? $lastOrder;
        $money = fn ($amount) => number_format((float) $amount, 0, ',', ' ').' FCFA';
    @endphp

    <div class="space-y-8">
        {{-- COMMANDE EN VEDETTE : celle en cours, sinon la dernière ; à défaut,
             une invitation à la première. --}}
        @if ($featuredOrder)
            <section class="relative overflow-hidden border border-brand-ink/10 bg-white p-6 sm:p-8" data-reveal>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">
                            {{ $activeOrder ? 'Votre commande en cours' : 'Votre dernière commande' }}
                        </span>
                        <h2 class="mt-2 font-display text-2xl sm:text-3xl text-brand-ink">{{ $featuredOrder->number }}</h2>
                        <p class="mt-1 text-sm text-brand-muted">
                            Passée le {{ $featuredOrder->created_at->translatedFormat('j F Y') }}
                            · {{ $featuredOrder->items->sum('quantity') }} {{ $featuredOrder->items->sum('quantity') > 1 ? 'articles' : 'article' }}
                            · {{ $money($featuredOrder->total) }}
                        </p>
                    </div>

                    <a href="{{ route('account.orders.show', $featuredOrder) }}" class="group inline-flex shrink-0 items-center gap-2 self-start bg-brand-ink px-6 py-3 text-xs font-medium uppercase tracking-[0.15em] text-brand-surface transition hover:bg-brand-signature">
                        {{ $activeOrder ? 'Suivre ma commande' : 'Voir le détail' }}
                        <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>

                <x-account.order-progress :order="$featuredOrder" class="mt-8" />

                {{-- Vignettes des articles : on reconnaît sa commande d'un coup d'œil. --}}
                @php $items = $featuredOrder->items; @endphp
                <div class="mt-8 flex items-center gap-3 border-t border-brand-ink/10 pt-6">
                    @foreach ($items->take(4) as $item)
                        @php $thumb = $item->product?->primaryImage()?->sizedUrl('thumb'); @endphp
                        <div class="h-16 w-14 shrink-0 overflow-hidden bg-brand-parchment" title="{{ $item->product_name }}">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $item->product_name }}" loading="lazy" class="h-full w-full object-cover">
                            @else
                                <x-shop.image-placeholder :category="null" />
                            @endif
                        </div>
                    @endforeach
                    @if ($items->count() > 4)
                        <span class="flex h-16 w-14 shrink-0 items-center justify-center border border-brand-ink/10 text-sm text-brand-muted">+{{ $items->count() - 4 }}</span>
                    @endif
                    <p class="ml-2 min-w-0 truncate text-sm text-brand-muted">{{ $items->pluck('product_name')->implode(', ') }}</p>
                </div>
            </section>
        @else
            <section class="relative overflow-hidden bg-brand-parchment/70 p-8 sm:p-10" data-reveal>
                <div class="passage-frame absolute inset-4"></div>
                <div class="relative max-w-lg">
                    <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Bienvenue chez vous</span>
                    <h2 class="mt-3 font-display text-3xl text-brand-ink">Votre première pièce vous attend</h2>
                    <p class="mt-3 text-brand-muted">Hijabs, foulards, cols, parfums : prenez le temps de choisir. Vos commandes apparaîtront ici, avec leur suivi pas à pas.</p>
                    <x-shop.cta :href="route('shop.index')" class="mt-6">Découvrir la sélection</x-shop.cta>
                </div>
            </section>
        @endif

        <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr),20rem]">
            {{-- COUPS DE CŒUR --}}
            <section data-reveal style="--reveal-delay: 100ms">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <div>
                        <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Rien qu'à vous</span>
                        <h2 class="mt-2 font-display text-2xl text-brand-ink">Vos coups de cœur</h2>
                    </div>
                    @if ($favoritesCount > 0)
                        <a href="{{ route('shop.wishlist') }}" class="whitespace-nowrap text-xs uppercase tracking-[0.2em] text-brand-ink/70 transition hover:text-brand-signature">
                            Tout voir ({{ $favoritesCount }}) →
                        </a>
                    @endif
                </div>

                @if ($favorites->isNotEmpty())
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach ($favorites as $product)
                            <x-shop.product-card :product="$product" />
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center gap-4 border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-accent/10 text-brand-signature">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                            </svg>
                        </span>
                        <p class="max-w-sm text-sm text-brand-muted">Touchez le cœur d'une pièce qui vous plaît : elle vous attendra ici, le temps d'y réfléchir.</p>
                        <a href="{{ route('shop.index', ['nouveautes' => 1]) }}" class="text-xs uppercase tracking-[0.2em] text-brand-ink underline-offset-4 hover:text-brand-signature hover:underline">Voir les nouveautés</a>
                    </div>
                @endif
            </section>

            <aside class="space-y-4" data-reveal style="--reveal-delay: 200ms">
                {{-- RACCOURCIS, avec ses vraies informations. --}}
                @php
                    $shortcuts = [
                        [
                            'route' => 'account.addresses.index',
                            'title' => 'Adresse de livraison',
                            'info' => $defaultAddress ? $defaultAddress->city.' — '.Str::limit($defaultAddress->address_line, 28) : 'Aucune adresse enregistrée',
                            'icon' => ['M15 10.5a3 3 0 11-6 0 3 3 0 016 0z', 'M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z'],
                        ],
                        [
                            'route' => 'shop.cart',
                            'title' => 'Mon panier',
                            'info' => $cartCount > 0 ? $cartCount.' '.($cartCount > 1 ? 'articles vous attendent' : 'article vous attend') : 'Votre panier est vide',
                            'icon' => ['M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z'],
                        ],
                        [
                            'route' => 'profile.edit',
                            'title' => 'Mes informations',
                            'info' => auth()->user()->email,
                            'icon' => ['M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
                        ],
                    ];
                @endphp

                @foreach ($shortcuts as $shortcut)
                    <a href="{{ route($shortcut['route']) }}" class="group flex items-center gap-4 border border-brand-ink/10 bg-white p-4 transition duration-300 hover:border-brand-signature/40 hover:shadow-lg">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-accent/10 text-brand-signature transition group-hover:bg-brand-signature group-hover:text-brand-surface">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                @foreach ($shortcut['icon'] as $path)
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                                @endforeach
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-display text-base text-brand-ink">{{ $shortcut['title'] }}</span>
                            <span class="block truncate text-xs text-brand-muted">{{ $shortcut['info'] }}</span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-brand-ink/20 transition group-hover:translate-x-1 group-hover:text-brand-signature" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                @endforeach

                {{-- PROFIL : jauge et prochains pas, masquée une fois tout complété. --}}
                @if ($profileCompletion < 100)
                    <div class="border border-brand-ink/10 bg-brand-parchment/50 p-5">
                        <div class="flex items-baseline justify-between">
                            <p class="font-display text-base text-brand-ink">Votre profil</p>
                            <p class="font-display text-xl text-brand-signature">{{ $profileCompletion }} %</p>
                        </div>
                        <div class="mt-3 h-1 w-full bg-brand-ink/10" role="progressbar" aria-valuenow="{{ $profileCompletion }}" aria-valuemin="0" aria-valuemax="100" aria-label="Profil complété">
                            <div class="h-full bg-brand-signature" style="width: {{ $profileCompletion }}%"></div>
                        </div>
                        <ul class="mt-4 space-y-2">
                            @foreach ($profileSteps as $step)
                                <li class="flex items-center gap-2 text-sm">
                                    @if ($step['done'])
                                        <svg class="h-4 w-4 shrink-0 text-brand-signature" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <span class="text-brand-muted line-through decoration-brand-ink/20">{{ $step['label'] }}</span>
                                    @else
                                        <span class="h-4 w-4 shrink-0 rounded-full border border-brand-ink/25" aria-hidden="true"></span>
                                        <a href="{{ route($step['route']) }}" class="text-brand-ink underline-offset-4 hover:text-brand-signature hover:underline">{{ $step['label'] }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</x-account-layout>

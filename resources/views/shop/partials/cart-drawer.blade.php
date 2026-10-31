{{-- Tiroir panier (slide-over) — s'ouvre depuis la droite via l'état Alpine
     `cartOpen` (défini sur <body>). Rendu côté serveur avec $headerCart
     (partagé au layout, lecture seule). --}}
<div x-show="cartOpen" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="cartOpen = false">
    {{-- Voile sombre --}}
    <div
        x-show="cartOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-brand-ink/50 backdrop-blur-sm"
        @click="cartOpen = false"
    ></div>

    {{-- Panneau --}}
    <div
        x-show="cartOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-brand-surface shadow-2xl"
    >
        <div class="flex items-center justify-between border-b border-brand-ink/10 px-6 py-5">
            <h2 class="flex items-center gap-2 font-display text-xl text-brand-ink">
                Votre panier
                @if ($headerCart && $headerCart->items->isNotEmpty())
                    <span class="text-sm text-brand-muted">({{ $headerCart->items->sum('quantity') }})</span>
                @endif
            </h2>
            <button type="button" @click="cartOpen = false" class="text-brand-ink/50 transition hover:text-brand-ink" aria-label="Fermer le panier">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        @if (! $headerCart || $headerCart->items->isEmpty())
            <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-accent/10 text-brand-signature">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                    </svg>
                </div>
                <p class="text-brand-ink">Votre panier est vide.</p>
                <p class="max-w-xs text-sm text-brand-muted">Laissez-vous inspirer par nos collections.</p>
                <a href="{{ route('shop.index') }}" class="mt-2 rounded-sm bg-brand-ink px-6 py-3 text-sm uppercase tracking-wide text-brand-surface transition hover:bg-brand-accent hover:text-brand-surface">
                    Découvrir le catalogue
                </a>
            </div>
        @else
            <div class="flex-1 divide-y divide-brand-ink/10 overflow-y-auto px-6">
                @foreach ($headerCart->items as $item)
                    @php
                        $variantLabel = collect($item->variant?->attributes ?? [])
                            ->map(fn ($value, $key) => ucfirst($key).' : '.$value)
                            ->implode(', ');
                    @endphp
                    <div class="flex gap-4 py-4">
                        <a href="{{ route('shop.product', $item->product) }}" class="h-20 w-20 shrink-0 overflow-hidden rounded-sm bg-brand-parchment">
                            @if ($image = $item->product->primaryImage())
                                <img src="{{ $image->sizedUrl('thumb') }}" alt="{{ $item->product->name }}" loading="lazy" class="h-full w-full object-cover object-center">
                            @else
                                <x-shop.image-placeholder :category="$item->product->category" />
                            @endif
                        </a>

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('shop.product', $item->product) }}" class="block truncate text-sm text-brand-ink hover:text-brand-signature">
                                {{ $item->product->name }}
                            </a>
                            @if ($variantLabel)
                                <p class="mt-0.5 truncate text-xs text-brand-muted">{{ $variantLabel }}</p>
                            @endif
                            <p class="mt-1 text-xs text-brand-muted">
                                Qté {{ $item->quantity }} × {{ number_format($item->unitPrice(), 0, ',', ' ') }} FCFA
                            </p>
                            @if ($reason = $item->unavailableReason())
                                <p class="mt-1 text-xs text-brand-signature">{{ $reason }}</p>
                            @endif
                            <p class="mt-1 text-sm font-medium text-brand-ink">
                                {{ number_format($item->lineTotal(), 0, ',', ' ') }} FCFA
                            </p>
                        </div>

                        <form method="POST" action="{{ route('shop.cart.destroy', $item) }}" class="shrink-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-brand-muted transition hover:text-brand-signature" aria-label="Retirer du panier">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="space-y-4 border-t border-brand-ink/10 px-6 py-5">
                <div class="flex items-center justify-between">
                    <span class="text-brand-muted">Total</span>
                    <span class="font-display text-xl text-brand-ink">{{ number_format($headerCart->total(), 0, ',', ' ') }} FCFA</span>
                </div>
                <a href="{{ route('shop.cart') }}" class="block w-full rounded-sm bg-brand-ink py-3 text-center text-sm uppercase tracking-wide text-brand-surface transition hover:bg-brand-accent hover:text-brand-surface">
                    Voir le panier
                </a>
                <button type="button" @click="cartOpen = false" class="block w-full text-center text-sm text-brand-muted transition hover:text-brand-ink">
                    Continuer mes achats
                </button>
            </div>
        @endif
    </div>
</div>

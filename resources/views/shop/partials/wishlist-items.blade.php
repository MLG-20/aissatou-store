{{-- Contenu de la liste d'envies, partagé entre la page boutique (visiteuse)
     et l'espace cliente (connectée). --}}
@if (session('status'))
    <p class="mb-6 border-l-2 border-brand-signature bg-brand-parchment/60 px-4 py-3 text-sm text-brand-ink">
        {{ session('status') }}
    </p>
@endif

@if ($errors->any())
    <p class="mb-6 text-sm text-brand-signature">{{ $errors->first() }}</p>
@endif

@if ($items->isEmpty())
    <div class="flex flex-col items-center gap-4 border border-dashed border-brand-ink/20 px-6 py-14 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-accent/10 text-brand-signature">
            <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
            </svg>
        </span>
        <h3 class="font-display text-xl text-brand-ink">Rien de mis de côté pour l'instant</h3>
        <p class="max-w-md text-sm text-brand-muted">
            Touchez le cœur d'une pièce qui vous plaît : elle vous attendra ici, le temps d'y réfléchir{{ auth()->check() ? '' : ', même sans compte' }}.
        </p>
        <x-shop.cta :href="route('shop.index', ['nouveautes' => 1])" class="mt-2">Voir les nouveautés</x-shop.cta>
    </div>
@else
    <div class="grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($items as $item)
            <div>
                <x-shop.product-card :product="$item->product" />
                <form method="POST" action="{{ route('shop.wishlist.destroy', $item->product) }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 text-xs uppercase tracking-[0.15em] text-brand-muted transition hover:text-brand-signature">
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Retirer
                    </button>
                </form>
            </div>
        @endforeach
    </div>
@endif

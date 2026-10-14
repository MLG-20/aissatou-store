<x-shop-layout :title="'Ma liste d\'envies — '.config('app.name')">
    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8">
        <p class="text-xs uppercase tracking-[0.25em] text-brand-signature">Mis de côté</p>
        <h1 class="font-display text-3xl mt-2">Ma liste d'envies</h1>

        @if (session('status'))
            <p class="mt-6 text-sm text-brand-ink bg-brand-parchment border border-brand-signature/40 px-4 py-3 rounded-sm">
                {{ session('status') }}
            </p>
        @endif

        @if ($errors->any())
            <p class="mt-6 text-sm text-brand-signature">{{ $errors->first() }}</p>
        @endif

        @if ($items->isEmpty())
            <div class="mt-8 rounded-sm border border-brand-ink/10 bg-white p-10 sm:p-14 text-center">
                <h2 class="font-display text-xl text-brand-ink">Rien de mis de côté pour l'instant</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-brand-muted">
                    Touchez le cœur d'une fiche produit pour la retrouver ici, même sans compte.
                </p>
                <a href="{{ route('shop.index') }}" class="mt-6 inline-block rounded-sm bg-brand-ink px-6 py-3 text-sm uppercase tracking-wide text-brand-surface transition hover:bg-brand-accent">
                    Découvrir le catalogue
                </a>
            </div>
        @else
            <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4">
                @foreach ($items as $item)
                    <div>
                        <x-shop.product-card :product="$item->product" />
                        <form method="POST" action="{{ route('shop.wishlist.destroy', $item->product) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs uppercase tracking-wide underline text-brand-muted hover:text-brand-signature">
                                Retirer
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-shop-layout>

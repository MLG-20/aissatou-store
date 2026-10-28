<x-shop-layout :title="'Panier — '.config('app.name')">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8">
        <h1 class="font-display text-3xl mb-6">Votre panier</h1>

        @if (session('status'))
            <p class="mb-6 text-sm text-brand-ink bg-brand-parchment border border-brand-signature/40 px-4 py-3 rounded-sm">
                {{ session('status') }}
            </p>
        @endif

        @if ($errors->any())
            <p class="mb-6 text-sm text-brand-signature">{{ $errors->first() }}</p>
        @endif

        @if ($cart->items->isEmpty())
            <p class="text-brand-muted">Votre panier est vide.</p>
            <a href="{{ route('shop.index') }}" class="inline-block mt-4 text-sm underline hover:text-brand-signature">
                Continuer mes achats
            </a>
        @else
            <div class="divide-y divide-brand-ink/10 border-y border-brand-ink/10">
                @foreach ($cart->items as $item)
                    @php
                        $variantLabel = collect($item->variant?->attributes ?? [])
                            ->map(fn ($value, $key) => ucfirst($key).' : '.$value)
                            ->implode(', ');
                    @endphp
                    <div class="py-5 flex items-center gap-4">
                        <div class="w-20 h-20 flex-shrink-0 rounded-sm overflow-hidden bg-brand-parchment">
                            @if ($image = $item->product->primaryImage())
                                <img
                                    src="{{ $image->sizedUrl('thumb') }}"
                                    alt="{{ $item->product->name }}"
                                    loading="lazy"
                                    class="h-full w-full object-cover object-center"
                                >
                            @else
                                <x-shop.image-placeholder :category="$item->product->category" />
                            @endif
                        </div>

                        <div class="flex-1 min-w-0">
                            <a href="{{ route('shop.product', $item->product) }}" class="text-sm text-brand-ink hover:text-brand-signature">
                                {{ $item->product->name }}
                            </a>
                            @if ($variantLabel)
                                <p class="text-xs text-brand-muted mt-0.5">{{ $variantLabel }}</p>
                            @endif

                            @unless ($item->isAvailable())
                                <p class="text-xs text-brand-signature mt-1">Cet article n'est plus disponible.</p>
                            @endunless

                            <p class="text-sm text-brand-ink mt-1">
                                {{ number_format($item->unitPrice(), 0, ',', ' ') }} FCFA
                            </p>
                        </div>

                        <form method="POST" action="{{ route('shop.cart.update', $item) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <input
                                type="number"
                                name="quantity"
                                value="{{ $item->quantity }}"
                                min="0"
                                max="50"
                                class="w-16 rounded-sm border border-brand-ink/20 focus:border-brand-signature focus:ring-brand-accent text-sm"
                            >
                            <button type="submit" class="text-xs uppercase tracking-wide underline hover:text-brand-signature">
                                Mettre à jour
                            </button>
                        </form>

                        <p class="w-24 text-right text-sm font-medium text-brand-ink">
                            {{ number_format($item->lineTotal(), 0, ',', ' ') }} FCFA
                        </p>

                        <form method="POST" action="{{ route('shop.cart.destroy', $item) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-brand-muted hover:text-brand-signature" aria-label="Supprimer">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex flex-col items-end gap-4">
                <p class="text-lg font-display">
                    Total : {{ number_format($cart->total(), 0, ',', ' ') }} FCFA
                </p>
                {{-- Invitée : le middleware `auth` renvoie à la connexion puis
                     ramène ici au tunnel (redirection « intended »). --}}
                <x-shop.cta href="{{ route('shop.checkout') }}" variant="primary" class="w-full sm:w-auto text-center">
                    Passer commande
                </x-shop.cta>

                {{-- Alternative pour qui préfère commander en discutant : le
                     panier part pré-rempli (total d'abord, au cas où le texte
                     serait tronqué par /whatsapp). --}}
                @if (filled(\App\Models\Content\SiteSetting::current()->contact_whatsapp))
                    @php
                        $whatsappText = "Bonjour, je souhaite commander ces articles (total ".number_format($cart->total(), 0, ',', ' ')." FCFA) :\n"
                            .$cart->items->map(fn ($item) => '- '.$item->product->name
                                .($item->variant?->attributes ? ' ('.collect($item->variant->attributes)->implode(', ').')' : '')
                                .' × '.$item->quantity)->implode("\n");
                    @endphp
                    <a
                        href="{{ route('shop.whatsapp', ['texte' => $whatsappText]) }}"
                        target="_blank"
                        rel="noopener"
                        data-whatsapp-cta
                        class="text-sm underline text-brand-muted hover:text-brand-signature"
                    >
                        Vous préférez commander sur WhatsApp ?
                    </a>
                @endif
            </div>
        @endif
    </div>
</x-shop-layout>

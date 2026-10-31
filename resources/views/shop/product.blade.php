@php
    $images = $product->images;
    $variants = $product->variants;
    $cheapestZone = $deliveryZones->sortBy('fee')->first();
    $formatFcfa = fn ($amount) => number_format($amount, 0, ',', ' ').' FCFA';
    $whatsappText = 'Bonjour, je suis intéressée par « '.$product->name.' » ('.$formatFcfa($product->price).'). '.route('shop.product', $product);
@endphp

<x-shop-layout :title="$product->name.' — '.config('app.name')">
    <div
        class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8"
        x-data="{
            activeImage: 0,
            touchStartX: 0,
            // Grandes versions des photos, pour la vue plein écran (lightbox).
            zoomImages: {{ Illuminate\Support\Js::from($images->map->sizedUrl('large')->values()) }},
            lightboxOpen: false,
            zoomed: false,
            originX: 50,
            originY: 50,
            buyBar: false,
            init() {
                if (!('IntersectionObserver' in window)) return;
                // Zone d'observation prolongée très loin vers le bas : le bouton
                // n'en « sort » qu'en passant au-dessus de l'écran. Sans cela,
                // passer de « sous l'écran » à « au-dessus » ne change pas
                // l'état observé et la barre ne venait jamais.
                new IntersectionObserver(([entry]) => {
                    this.buyBar = !entry.isIntersecting;
                    // Les boutons flottants (WhatsApp, retour en haut) montent
                    // au-dessus de la barre plutôt que de la chevaucher.
                    document.documentElement.classList.toggle('has-buy-bar', this.buyBar);
                }, { rootMargin: '0px 0px 100000px 0px' }).observe(document.getElementById('product-cta'));
            },
            onSwipe(event, count) {
                const deltaX = event.changedTouches[0].screenX - this.touchStartX;
                if (Math.abs(deltaX) < 40 || count <= 1) return;
                this.activeImage = deltaX < 0
                    ? (this.activeImage + 1) % count
                    : (this.activeImage - 1 + count) % count;
            },
            openLightbox() {
                if (this.zoomImages.length === 0) return;
                this.zoomed = false;
                this.lightboxOpen = true;
            },
            step(direction) {
                const count = this.zoomImages.length;
                if (count <= 1) return;
                this.zoomed = false;
                this.activeImage = (this.activeImage + direction + count) % count;
            },
            // La loupe suit le pointeur : on convertit sa position dans le cadre
            // en pourcentage, qui devient l'origine de l'agrandissement.
            track(event) {
                if (!this.zoomed) return;
                const rect = event.currentTarget.getBoundingClientRect();
                this.originX = ((event.clientX - rect.left) / rect.width) * 100;
                this.originY = ((event.clientY - rect.top) / rect.height) * 100;
            },
        }"
    >
        <nav class="text-sm text-brand-muted mb-6">
            <a href="{{ route('shop.category', $product->category) }}" class="hover:text-brand-ink">
                {{ $product->category->name }}
            </a>
            <span class="mx-1">/</span>
            <span class="text-brand-ink">{{ $product->name }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16">
            {{-- La galerie reste à l'écran pendant qu'on lit la colonne d'achat
                 (volets dépliés compris) : la pièce ne quitte jamais le regard. --}}
            <div class="lg:sticky lg:top-28 lg:self-start">
                <div
                    class="group relative aspect-[3/4] w-full overflow-hidden rounded-sm bg-brand-parchment touch-pan-y {{ $images->isNotEmpty() ? 'cursor-zoom-in' : '' }}"
                    x-on:touchstart="touchStartX = $event.changedTouches[0].screenX"
                    x-on:touchend="onSwipe($event, {{ $images->count() }})"
                    @if ($images->isNotEmpty()) @click="openLightbox()" @endif
                >
                    @forelse ($images as $index => $image)
                        <img
                            x-show="activeImage === {{ $index }}"
                            x-cloak
                            x-transition.opacity.duration.300ms
                            src="{{ $image->sizedUrl('large') }}"
                            srcset="{{ $image->sizedUrl('medium') }} 800w, {{ $image->sizedUrl('large') }} 1400w"
                            sizes="(min-width: 1024px) 50vw, 100vw"
                            alt="{{ $product->name }}"
                            loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                            class="h-full w-full object-cover object-center select-none"
                            draggable="false"
                        >
                    @empty
                        <x-shop.image-placeholder :category="$product->category" />
                    @endforelse

                    {{-- Indice « cliquer pour agrandir », discret, au survol
                         seulement — la p.9 proscrit les ornements permanents. --}}
                    @if ($images->isNotEmpty())
                        <span class="pointer-events-none absolute bottom-3 right-3 flex items-center gap-1.5 rounded-sm bg-brand-ink/60 px-2.5 py-1.5 text-[0.7rem] uppercase tracking-[0.12em] text-brand-surface opacity-0 backdrop-blur-sm transition group-hover:opacity-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6" />
                            </svg>
                            Agrandir
                        </span>
                    @endif
                </div>

                @if ($images->count() > 1)
                    <div class="mt-4 flex gap-3 overflow-x-auto">
                        @foreach ($images as $index => $image)
                            <button
                                type="button"
                                @click="activeImage = {{ $index }}"
                                class="w-16 h-16 flex-shrink-0 rounded-sm overflow-hidden border"
                                :class="activeImage === {{ $index }} ? 'border-brand-signature' : 'border-brand-ink/10'"
                            >
                                <img
                                    src="{{ $image->sizedUrl('thumb') }}"
                                    alt="{{ $product->name }}"
                                    loading="lazy"
                                    class="h-full w-full object-cover object-center"
                                >
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- HIÉRARCHIE PRODUIT de la p.3, dans l'ordre : 01 catégorie,
                 02 nom, 03 matière/bénéfice, 04 prix, 05 signal. La fiche
                 n'affichait ni le surtitre de catégorie, ni la matière, ni le
                 signal : trois des cinq niveaux manquaient, alors que la carte
                 produit, elle, en montrait déjà une partie. --}}
            <div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('shop.category', $product->category) }}" class="text-[0.75rem] uppercase tracking-[0.2em] text-brand-muted transition hover:text-brand-signature">
                        {{ $product->category->name }}
                    </a>

                    @if ($product->label)
                        <span class="px-2.5 py-1 text-[0.7rem] font-medium uppercase tracking-[0.15em] {{ $product->label->badgeClasses() }}">
                            {{ $product->label->label() }}
                        </span>
                    @endif
                </div>

                <h1 class="mt-3 font-display text-4xl sm:text-5xl leading-[1.05] text-brand-ink">{{ $product->name }}</h1>

                @if ($product->material_tagline)
                    <p class="mt-2 text-brand-muted">{{ $product->material_tagline }}</p>
                @endif
                @if ($product->material)
                    <p class="mt-2">
                        <a href="{{ route('shop.index', ['material_id' => $product->material_id]) }}" class="text-xs uppercase tracking-[0.18em] text-brand-signature underline hover:text-brand-ink">
                            Voir tout : {{ $product->material->name }}
                        </a>
                    </p>
                @endif

                <div class="mt-5 flex items-baseline gap-4 border-t border-brand-ink/10 pt-5">
                    <p class="font-display text-2xl text-brand-ink">{{ $formatFcfa($product->price) }}</p>

                    @if ($product->isInStock())
                        <p class="inline-flex items-center gap-1.5 text-xs uppercase tracking-[0.15em] text-brand-muted">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-sage" aria-hidden="true"></span>
                            En stock
                        </p>
                    @else
                        <p class="text-xs uppercase tracking-[0.15em] text-brand-signature">Rupture de stock</p>
                    @endif
                </div>

                @if ($product->description)
                    <p class="mt-6 leading-relaxed text-brand-ink/80 whitespace-pre-line">{{ $product->description }}</p>
                @endif

                @if ($errors->has('variant_id') || $errors->has('quantity'))
                    <p class="mt-4 text-sm text-brand-signature">
                        {{ $errors->first('variant_id') ?: $errors->first('quantity') }}
                    </p>
                @endif

                <form id="product-form" method="POST" action="{{ route('shop.cart.store') }}" class="mt-8">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    {{-- VARIANTES VISIBLES.

                         Elles étaient enfermées dans un <select> : il fallait
                         ouvrir la liste pour savoir si la pièce existait dans une
                         autre couleur. La p.12 range pourtant la variante dans ce
                         qui se lit d'emblée — « visuel, catégorie, nom, prix et
                         variante » — et les cartes de la p.11 montrent les
                         coloris directement.

                         Ce sont des boutons radio stylés, pas un composant JS :
                         le formulaire poste toujours `variant_id`, donc rien ne
                         change côté serveur, et la page reste utilisable sans
                         JavaScript. --}}
                    @if ($variants->isNotEmpty())
                        @php
                            $selectableVariants = $variants->filter(fn ($variant) => $variant->stock > 0);
                            // Une seule variante disponible : on la présélectionne
                            // plutôt que d'imposer un clic sur un choix unique.
                            $onlyVariant = $selectableVariants->count() === 1 ? $selectableVariants->first() : null;
                        @endphp

                        <fieldset>
                            <legend class="mb-3 text-xs uppercase tracking-wide text-brand-muted">
                                Choisir une variante
                            </legend>

                            <div class="flex flex-wrap gap-2">
                                @foreach ($variants as $variant)
                                    @php
                                        $price = $variant->price_override ?? $product->price;
                                        $swatch = $variant->swatch();
                                        $outOfStock = $variant->stock <= 0;

                                        $label = collect($variant->getAttribute('attributes') ?? [])
                                            // Quand la pastille est affichée, elle dit déjà
                                            // qu'il s'agit d'une couleur : « Couleur : Ivoire »
                                            // juste à côté du rond ivoire n'apprend rien.
                                            ->map(fn ($value, $key) => $swatch && $value === $variant->colourValue()
                                                ? $value
                                                : ucfirst($key).' : '.$value)
                                            ->implode(', ');
                                    @endphp

                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="variant_id"
                                            value="{{ $variant->id }}"
                                            required
                                            class="peer sr-only"
                                            @disabled($outOfStock)
                                            @checked($onlyVariant?->is($variant))
                                        >

                                        <span class="flex items-center gap-2 rounded-sm border border-brand-ink/20 px-3 py-2 text-sm text-brand-ink transition peer-checked:border-brand-signature peer-checked:bg-brand-parchment peer-focus-visible:ring-2 peer-focus-visible:ring-brand-accent peer-disabled:opacity-40 {{ $outOfStock ? 'line-through' : '' }}">
                                            @if ($swatch)
                                                <span class="h-3.5 w-3.5 rounded-full border border-brand-ink/15" style="background-color: {{ $swatch }}" aria-hidden="true"></span>
                                            @endif

                                            <span>{{ $label ?: $variant->sku }}</span>

                                            {{-- Le prix n'est rappelé que s'il diffère
                                                 de celui du produit : le répéter à
                                                 l'identique sur chaque variante
                                                 alourdirait sans rien apprendre. --}}
                                            @if ($variant->price_override)
                                                <span class="text-brand-muted">{{ number_format($price, 0, ',', ' ') }} FCFA</span>
                                            @endif

                                            @if ($outOfStock)
                                                <span class="text-xs text-brand-muted">(épuisé)</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <div class="mt-4 flex items-center gap-4">
                        <label for="quantity" class="text-xs uppercase tracking-wide text-brand-muted">Quantité</label>
                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="1"
                            min="1"
                            max="50"
                            class="w-20 rounded-sm border border-brand-ink/20 focus:border-brand-signature focus:ring-brand-accent text-sm"
                        >
                    </div>

                    {{-- Un seul CTA Garance par écran (rapport p.13) : « Acheter
                         maintenant » (décision Ahmed, 2026-10-31), qui met l'article
                         au panier et mène droit à la commande. « Ajouter au panier »
                         passe en contour, l'action secondaire. --}}
                    <div id="product-cta" class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <x-shop.cta variant="primary" name="buy_now" value="1" class="w-full sm:w-auto" :disabled="! $product->isInStock()">
                            Acheter maintenant
                        </x-shop.cta>
                        <x-shop.cta class="w-full sm:w-auto" :disabled="! $product->isInStock()">
                            Ajouter au panier
                        </x-shop.cta>
                    </div>
                </form>

                {{-- Action secondaire (contour, jamais Garance) : mettre de côté. --}}
                <form method="POST" action="{{ route('shop.wishlist.toggle', $product) }}" class="mt-3">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-sm border border-brand-ink/30 px-6 py-3 text-xs font-medium uppercase tracking-[0.15em] text-brand-ink transition duration-300 hover:border-brand-signature hover:text-brand-signature"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="{{ ($inWishlist ?? false) ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                        </svg>
                        {{ ($inWishlist ?? false) ? 'Dans votre liste' : 'Ajouter à ma liste' }}
                    </button>
                </form>

                {{-- RÉASSURANCE au pied du bouton, là où se prend la décision :
                     les arguments de l'accueil (mode de paiement en tête)
                     ne servent à rien s'ils sont loin de l'achat. Tout est tiré
                     des vrais réglages (zones, moyens de paiement, WhatsApp). --}}
                <ul class="mt-8 grid gap-px overflow-hidden rounded-sm border border-brand-ink/10 bg-brand-ink/10 sm:grid-cols-3">
                    @if ($cheapestZone)
                        <li class="flex items-start gap-3 bg-brand-surface p-4">
                            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-brand-signature" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                            </svg>
                            <span class="text-xs leading-snug text-brand-muted">
                                <span class="block font-medium text-brand-ink">Livraison dès {{ $formatFcfa($cheapestZone->fee) }}</span>
                                {{ $cheapestZone->name }}{{ $cheapestZone->delay_label ? ', '.$cheapestZone->delay_label : '' }}
                            </span>
                        </li>
                    @endif
                    <li class="flex items-start gap-3 bg-brand-surface p-4">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-brand-signature" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                        <span class="text-xs leading-snug text-brand-muted">
                            @if ($cashOnDelivery)
                                <span class="block font-medium text-brand-ink">Payez à la réception</span>
                                {{ $paymentMethods->count() > 1 ? 'ou en ligne : '.$paymentMethods->last() : 'en toute confiance' }}
                            @else
                                <span class="block font-medium text-brand-ink">Paiement sécurisé en ligne</span>
                                {{ $paymentMethods->implode(', ') ?: 'au moment de commander' }}
                            @endif
                        </span>
                    </li>
                    @if ($whatsappAvailable)
                        <li class="bg-brand-surface">
                            <a
                                href="{{ route('shop.whatsapp', ['texte' => $whatsappText]) }}"
                                target="_blank"
                                rel="noopener"
                                data-whatsapp-cta
                                class="group flex h-full items-start gap-3 p-4 transition hover:bg-brand-parchment/60"
                            >
                                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-brand-signature" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                </svg>
                                <span class="text-xs leading-snug text-brand-muted">
                                    <span class="block font-medium text-brand-ink underline decoration-brand-ink/20 underline-offset-4 transition group-hover:decoration-brand-accent">Une question ?</span>
                                    Une conseillère vous répond
                                </span>
                            </a>
                        </li>
                    @endif
                </ul>

                {{-- VOLETS : l'information utile sans allonger la colonne. Des
                     <details> natifs — accessibles au clavier et lisibles sans
                     JavaScript ; l'ouverture est animée en CSS là où le
                     navigateur le permet (.product-fold). --}}
                <div class="mt-10 border-t border-brand-ink/10">
                    @if ($product->material || $product->material_tagline)
                        <details class="product-fold group border-b border-brand-ink/10" open>
                            <summary class="flex cursor-pointer list-none items-center justify-between py-5 text-xs font-medium uppercase tracking-[0.2em] text-brand-ink [&::-webkit-details-marker]:hidden">
                                {{ $product->material ? 'Matière' : 'Détails' }}
                                <span class="relative h-3 w-3" aria-hidden="true">
                                    <span class="absolute left-0 top-1/2 h-px w-3 bg-current"></span>
                                    <span class="absolute left-1/2 top-0 h-3 w-px bg-current transition-transform duration-300 group-open:rotate-90 group-open:opacity-0"></span>
                                </span>
                            </summary>
                            <div class="pb-6 text-sm leading-relaxed text-brand-muted">
                                @if ($product->material)
                                    <p><span class="text-brand-ink">{{ $product->material->name }}</span>{{ $product->material_tagline ? ' — '.$product->material_tagline : '' }}</p>
                                    <a href="{{ route('shop.index', ['material_id' => $product->material_id]) }}" class="mt-3 inline-block text-xs uppercase tracking-[0.18em] text-brand-signature underline underline-offset-4 hover:text-brand-ink">
                                        Toutes les pièces en {{ $product->material->name }}
                                    </a>
                                @else
                                    <p>{{ $product->material_tagline }}</p>
                                @endif
                            </div>
                        </details>
                    @endif

                    @if ($deliveryZones->isNotEmpty())
                        <details class="product-fold group border-b border-brand-ink/10">
                            <summary class="flex cursor-pointer list-none items-center justify-between py-5 text-xs font-medium uppercase tracking-[0.2em] text-brand-ink [&::-webkit-details-marker]:hidden">
                                Livraison
                                <span class="relative h-3 w-3" aria-hidden="true">
                                    <span class="absolute left-0 top-1/2 h-px w-3 bg-current"></span>
                                    <span class="absolute left-1/2 top-0 h-3 w-px bg-current transition-transform duration-300 group-open:rotate-90 group-open:opacity-0"></span>
                                </span>
                            </summary>
                            <div class="pb-6">
                                <dl class="divide-y divide-brand-ink/5 text-sm">
                                    @foreach ($deliveryZones as $zone)
                                        <div class="flex items-baseline justify-between gap-4 py-2.5">
                                            <dt class="text-brand-ink">
                                                {{ $zone->name }}
                                                @if ($zone->delay_label)
                                                    <span class="ml-1 text-xs text-brand-muted">{{ $zone->delay_label }}</span>
                                                @endif
                                            </dt>
                                            <dd class="whitespace-nowrap text-brand-muted">{{ $formatFcfa($zone->fee) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                                <p class="mt-3 text-xs text-brand-muted">Le suivi de votre commande est visible à tout moment dans votre espace.</p>
                            </div>
                        </details>
                    @endif

                    <details class="product-fold group border-b border-brand-ink/10">
                        <summary class="flex cursor-pointer list-none items-center justify-between py-5 text-xs font-medium uppercase tracking-[0.2em] text-brand-ink [&::-webkit-details-marker]:hidden">
                            Paiement
                            <span class="relative h-3 w-3" aria-hidden="true">
                                <span class="absolute left-0 top-1/2 h-px w-3 bg-current"></span>
                                <span class="absolute left-1/2 top-0 h-3 w-px bg-current transition-transform duration-300 group-open:rotate-90 group-open:opacity-0"></span>
                            </span>
                        </summary>
                        <ul class="space-y-2 pb-6 text-sm text-brand-muted">
                            @foreach ($paymentMethods as $method)
                                <li class="flex items-center gap-3">
                                    <span class="h-px w-4 bg-brand-signature/50" aria-hidden="true"></span>
                                    {{ $method }}
                                </li>
                            @endforeach
                        </ul>
                    </details>
                </div>
            </div>
        </div>

        {{-- LIGHTBOX — vue plein écran de la photo pour mieux juger la matière et
             le tombé (principe non négociable n°3). Clic sur l'image : bascule
             l'agrandissement, la loupe suivant le pointeur. Fond Encre, sans
             ornement : c'est le produit qui domine. --}}
        @if ($images->isNotEmpty())
            <div
                x-show="lightboxOpen"
                x-cloak
                x-transition.opacity.duration.200ms
                class="fixed inset-0 z-50 flex items-center justify-center bg-brand-ink/95"
                @keydown.escape.window="lightboxOpen = false"
                @keydown.arrow-right.window="lightboxOpen && step(1)"
                @keydown.arrow-left.window="lightboxOpen && step(-1)"
                role="dialog"
                aria-modal="true"
                aria-label="Photo agrandie de {{ $product->name }}"
            >
                <button
                    type="button"
                    @click="lightboxOpen = false"
                    class="absolute right-4 top-4 z-10 p-2 text-brand-surface/70 transition hover:text-brand-surface"
                    aria-label="Fermer"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                {{-- Flèches précédent / suivant, seulement s'il y a plusieurs photos. --}}
                <template x-if="zoomImages.length > 1">
                    <div>
                        <button type="button" @click="step(-1)" class="absolute left-4 top-1/2 -translate-y-1/2 p-2 text-brand-surface/70 transition hover:text-brand-surface" aria-label="Photo précédente">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </button>
                        <button type="button" @click="step(1)" class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-brand-surface/70 transition hover:text-brand-surface" aria-label="Photo suivante">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </button>
                    </div>
                </template>

                {{-- Cadre de l'image : le clic bascule le zoom, le déplacement du
                     pointeur repositionne la loupe. `overflow-hidden` garde
                     l'agrandissement dans le cadre. --}}
                <div
                    class="relative flex h-full w-full items-center justify-center overflow-hidden p-6 sm:p-12"
                    :class="zoomed ? 'cursor-zoom-out' : 'cursor-zoom-in'"
                    @click.self="zoomed = !zoomed"
                    @mousemove="track($event)"
                >
                    <img
                        :src="zoomImages[activeImage]"
                        alt="{{ $product->name }}"
                        class="max-h-full max-w-full select-none object-contain transition-transform duration-200"
                        :class="zoomed ? 'scale-[2.2]' : 'scale-100'"
                        :style="zoomed ? `transform-origin: ${originX}% ${originY}%` : ''"
                        @click="zoomed = !zoomed"
                        @mousemove="track($event)"
                        draggable="false"
                    >
                </div>
            </div>
        @endif

        {{-- BARRE D'ACHAT MOBILE — apparaît une fois le bouton principal sorti
             de l'écran par le haut, et s'efface quand il revient : il n'y a
             jamais deux « Acheter maintenant » visibles à la fois (un seul CTA
             Garance par écran). Elle soumet le MÊME formulaire (`form=`), donc
             le choix de variante obligatoire reste vérifié par le navigateur. --}}
        <div
            x-show="buyBar"
            x-cloak
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="translate-y-full"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-end="translate-y-full"
            class="fixed inset-x-0 bottom-0 z-40 border-t border-brand-ink/10 bg-brand-surface/95 px-4 py-3 shadow-[0_-8px_24px_-12px_rgba(23,21,27,0.25)] backdrop-blur lg:hidden"
        >
            <div class="flex items-center gap-3">
                @if ($image = $product->primaryImage())
                    <img src="{{ $image->sizedUrl('thumb') }}" alt="" class="h-11 w-11 flex-shrink-0 rounded-sm object-cover">
                @endif
                <div class="min-w-0 flex-1">
                    <p class="truncate font-display text-base leading-tight text-brand-ink">{{ $product->name }}</p>
                    <p class="text-xs text-brand-muted">{{ $formatFcfa($product->price) }}</p>
                </div>
                <button
                    type="submit"
                    form="product-form"
                    @disabled(! $product->isInStock())
                    aria-label="Ajouter au panier"
                    class="flex-shrink-0 rounded-sm border border-brand-ink/30 p-3 text-brand-ink transition hover:border-brand-ink disabled:opacity-40"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                </button>
                <button
                    type="submit"
                    form="product-form"
                    name="buy_now"
                    value="1"
                    @disabled(! $product->isInStock())
                    class="flex-shrink-0 rounded-sm bg-brand-accent px-4 py-3 text-[0.7rem] font-medium uppercase tracking-[0.12em] text-brand-surface transition hover:bg-brand-signature disabled:opacity-40"
                >
                    Acheter
                </button>
            </div>
        </div>
    </div>

    @include('shop.partials.product-rail', ['eyebrow' => 'L\'accord parfait', 'title' => 'Complétez votre tenue', 'products' => $completeTheLook, 'muted' => true])
    @include('shop.partials.product-rail', ['eyebrow' => 'Dans le même univers', 'title' => 'Vous aimerez aussi', 'products' => $related, 'muted' => false])
    @include('shop.partials.product-rail', ['eyebrow' => 'Votre parcours', 'title' => 'Vus récemment', 'products' => $recentlyViewed, 'muted' => true])
</x-shop-layout>

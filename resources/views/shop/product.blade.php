@php
    $images = $product->images;
    $variants = $product->variants;
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div>
                <div
                    class="group relative aspect-square w-full overflow-hidden rounded-sm bg-brand-parchment touch-pan-y {{ $images->isNotEmpty() ? 'cursor-zoom-in' : '' }}"
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
                        <span class="pointer-events-none absolute bottom-3 right-3 flex items-center gap-1.5 rounded-sm bg-brand-ink/60 px-2.5 py-1.5 text-[0.6rem] uppercase tracking-[0.12em] text-brand-surface opacity-0 backdrop-blur-sm transition group-hover:opacity-100">
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
                    <a href="{{ route('shop.category', $product->category) }}" class="text-[0.65rem] uppercase tracking-[0.2em] text-brand-muted transition hover:text-brand-signature">
                        {{ $product->category->name }}
                    </a>

                    @if ($product->label)
                        <span class="px-2.5 py-1 text-[0.6rem] font-medium uppercase tracking-[0.15em] {{ $product->label->badgeClasses() }}">
                            {{ $product->label->label() }}
                        </span>
                    @endif
                </div>

                <h1 class="mt-2 font-display text-3xl">{{ $product->name }}</h1>

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

                <p class="mt-3 text-xl text-brand-ink">
                    {{ number_format($product->price, 0, ',', ' ') }} FCFA
                </p>

                @if ($product->isInStock())
                    <p class="mt-1 text-sm text-brand-muted">En stock</p>
                @else
                    <p class="mt-1 text-sm text-brand-signature">Rupture de stock</p>
                @endif

                @if ($product->description)
                    <p class="mt-6 text-brand-ink/80 whitespace-pre-line">{{ $product->description }}</p>
                @endif

                @if ($errors->has('variant_id') || $errors->has('quantity'))
                    <p class="mt-4 text-sm text-brand-signature">
                        {{ $errors->first('variant_id') ?: $errors->first('quantity') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('shop.cart.store') }}" class="mt-8">
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

                    {{-- « Ajouter au panier » est l'action principale UNIQUE de
                         cette page : elle doit donc être le CTA Garance (p.13).
                         Elle était en contour, c'est-à-dire au traitement réservé
                         aux actions secondaires — la fiche n'avait alors aucun
                         CTA principal, alors que le rapport en impose exactement
                         un par écran. --}}
                    <x-shop.cta variant="primary" class="mt-6 w-full sm:w-auto" :disabled="! $product->isInStock()">
                        Ajouter au panier
                    </x-shop.cta>
                </form>
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
    </div>
</x-shop-layout>

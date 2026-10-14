@props(['product'])

{{-- Carte produit de la maquette p.11 : « l'image domine, le produit occupe la
     première place ». D'où un visuel en portrait (3/4) qui occupe l'essentiel de
     la carte, et une zone de texte réduite à l'essentiel : catégorie, nom, prix.
     Angles nets (jeton RAYON 0-4 px du rapport, p.13). --}}
<a href="{{ route('shop.product', $product) }}" class="group block">
    <div class="relative aspect-[3/4] w-full overflow-hidden rounded-sm bg-brand-parchment">
        @if ($image = $product->primaryImage())
            <img
                src="{{ $image->sizedUrl('thumb') }}"
                srcset="{{ $image->sizedUrl('thumb') }} 480w, {{ $image->sizedUrl('medium') }} 800w"
                sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
                alt="{{ $product->name }}"
                loading="lazy"
                class="h-full w-full object-cover object-center transition duration-700 group-hover:scale-[1.03]"
            >
        @else
            <x-shop.image-placeholder :category="$product->category" />
        @endif

        {{-- Signal commercial, en haut à gauche comme sur la maquette. Absent la
             plupart du temps : le rapport interdit d'en faire une décoration. --}}
        @if ($product->label)
            <span class="absolute left-0 top-4 px-3 py-1.5 text-[0.6rem] font-medium uppercase tracking-[0.15em] {{ $product->label->badgeClasses() }}">
                {{ $product->label->label() }}
            </span>
        @endif

        @unless ($product->isInStock())
            <span class="absolute right-0 top-4 bg-brand-ink/80 px-3 py-1.5 text-[0.6rem] font-medium uppercase tracking-[0.15em] text-brand-surface">
                Épuisé
            </span>
        @endunless
    </div>

    {{-- HIÉRARCHIE PRODUIT de la p.3, dans son ordre exact : catégorie, nom,
         matière/bénéfice, prix — puis les variantes, que la p.12 ajoute à la
         lecture produit (« visuel, catégorie, nom, prix et variante »). --}}
    <div class="mt-4 flex flex-col gap-1">
        <span class="text-[0.6rem] uppercase tracking-[0.18em] text-brand-muted">{{ $product->category->name }}</span>
        <h3 class="font-display text-lg leading-snug text-brand-ink">{{ $product->name }}</h3>

        @if ($product->material_tagline)
            <p class="text-xs leading-snug text-brand-muted">{{ $product->material_tagline }}</p>
        @endif

        <p class="text-sm text-brand-ink">{{ number_format($product->price, 0, ',', ' ') }} FCFA</p>

        @php
            // Une pastille par couleur DISTINCTE et reconnue. Deux variantes de
            // même couleur (deux tailles d'un même coloris) ne doivent pas
            // produire deux pastilles identiques.
            $swatches = $product->variants
                ->map(fn ($variant) => $variant->swatch())
                ->filter()
                ->unique()
                ->take(5);
        @endphp

        @if ($swatches->isNotEmpty())
            <div class="mt-1.5 flex items-center gap-1.5">
                @foreach ($swatches as $swatch)
                    {{-- Le liseré évite qu'une pastille claire (Ivoire, Blanc)
                         disparaisse sur le fond Parchemin. --}}
                    <span
                        class="h-3 w-3 rounded-full border border-brand-ink/15"
                        style="background-color: {{ $swatch }}"
                        aria-hidden="true"
                    ></span>
                @endforeach
            </div>
        @endif
    </div>
</a>

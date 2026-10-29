<x-shop-layout :title="config('app.name')" :transparent-header="true">
    {{--
        Hero : carrousel géré depuis l'admin (Filament > Contenu du site >
        Hero). Chaque slide a son image, son titre, son sous-titre et son
        bouton propres. Si aucune slide n'est configurée, on retombe sur la
        promesse de marque du rapport d'identité — le site n'est jamais vide
        avant le premier réglage en admin.
    --}}
    @php
        $defaultSlide = [
            'image' => null,
            // Promesse officielle de la marque (rapport d'identité p.2).
            'title' => "L'élégance dans la pudeur",
            'subtitle' => 'Une sélection de hijabs, foulards, accessoires et parfums pensée pour accompagner chaque silhouette avec distinction.',
            'cta_label' => 'Découvrir la collection',
            'cta_url' => route('shop.index'),
        ];
    @endphp

    {{-- Hauteur du hero, pour couper le moins de photo possible. Grand écran :
         au format 16:9 d'une photo paysage (dans la limite de l'écran), plus
         haut pour un portrait. Téléphone : presque tout l'écran dès qu'une
         photo verticale s'y affiche (portrait ou version téléphone). --}}
    @php
        $anyPortrait = $slides->contains(fn ($slide) => $slide->image_path && $slide->isPortrait());
        $anyPhonePortrait = $anyPortrait || $slides->contains(fn ($slide) => $slide->image_path && $slide->mobile_image_path);
    @endphp
    <section
        @class([
            'relative w-full text-brand-surface overflow-hidden md:min-h-[560px]',
            'min-h-[560px]' => ! $anyPhonePortrait,
            'min-h-[max(620px,92svh)]' => $anyPhonePortrait,
            'md:h-[min(100svh,56.25vw)]' => ! $anyPortrait,
            'lg:min-h-[680px] lg:h-[min(88vh,860px)]' => $anyPortrait,
        ])
        x-data="{ active: 0, count: {{ max($slides->count(), 1) }}, portraits: @js($slides->map(fn ($slide) => (bool) ($slide->image_path && $slide->isPortrait()))->values()) }"
        x-init="count > 1 && setInterval(() => active = (active + 1) % count, 6000)"
        x-effect="$dispatch('hero-light', portraits[active] ?? false)"
    >
        @if ($slides->isEmpty())
            <div class="absolute inset-0 bg-brand-ink">
                <x-shop.fold-backdrop />

                <div class="relative h-full max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 flex flex-col justify-center items-start gap-6 py-24">
                    <span class="text-[0.75rem] uppercase tracking-[0.4em] text-brand-surface/60">Aissatou Store</span>
                    <h1 class="font-display text-4xl sm:text-6xl lg:text-7xl leading-[1.05] max-w-3xl">{{ $defaultSlide['title'] }}</h1>
                    <p class="text-brand-surface/70 max-w-lg text-base sm:text-lg">{{ $defaultSlide['subtitle'] }}</p>

                    {{-- Un seul CTA Garance (règle du rapport p.13) ; le second reste
                         en contour. --}}
                    <div class="flex flex-wrap gap-3 mt-2">
                        <x-shop.cta :href="$defaultSlide['cta_url']" variant="primary">
                            {{ $defaultSlide['cta_label'] }}
                        </x-shop.cta>

                        <x-shop.cta :href="route('shop.index', ['nouveautes' => 1])" on-dark>
                            Explorer les nouveautés
                        </x-shop.cta>
                    </div>
                </div>
            </div>
        @else
            @foreach ($slides as $index => $slide)
                {{-- Une photo paysage couvre tout le hero (le point focal choisit
                     la partie gardée). Un portrait aussi, sauf sur grand écran :
                     étiré sur toute la largeur, il perdrait le haut et le bas.
                     Il y occupe alors la moitié droite (à peine recadré, le
                     point focal choisit quoi garder), le texte en Encre sur la
                     moitié gauche claire ; l'en-tête passe alors en opaque
                     (événement `hero-light`, cf. layouts/shop). --}}
                @php
                    $portrait = $slide->image_path && $slide->isPortrait();
                    // Photo verticale affichée sur téléphone : texte posé en bas.
                    $phonePortrait = $portrait || ($slide->image_path && $slide->mobile_image_path);
                @endphp

                <div
                    x-show="active === {{ $index }}"
                    x-cloak
                    x-transition.opacity.duration.500ms
                    @class(['absolute inset-0 overflow-hidden bg-brand-ink', 'xl:bg-brand-surface' => $portrait])
                >
                    {{-- Vraie image responsive (charte photo, App\Support\ImageSlot) :
                         le navigateur choisit la taille utile à l'écran (2 400 px
                         sur grand écran haute densité, 800 px sur téléphone), la
                         version téléphone dédiée si elle existe, et le point focal
                         garde le sujet dans le cadre. Lent zoom arrière à
                         l'apparition (« Ken Burns »), sauf animations réduites. --}}
                    @if ($slide->image_path)
                        <picture @class(['absolute inset-0 block overflow-hidden', 'xl:left-1/2' => $portrait])>
                            @if ($slide->mobile_image_path)
                                <source
                                    media="(max-width: 767px)"
                                    srcset="{{ $slide->mobileSizedUrl('medium') }} 800w, {{ $slide->mobileSizedUrl('large') }} 1400w"
                                    sizes="100vw"
                                >
                            @endif
                            <img
                                src="{{ $slide->sizedUrl('large') }}"
                                srcset="{{ $slide->sizedUrl('medium') }} 800w, {{ $slide->sizedUrl('large') }} 1400w, {{ $slide->sizedUrl('xlarge') }} 2400w"
                                sizes="100vw"
                                alt=""
                                @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif
                                {{-- Le point focal est réglé sur la grande photo : la
                                     version téléphone dédiée, elle, reste centrée. --}}
                                @class([
                                    'hero-kenburns h-full w-full object-cover',
                                    '[object-position:var(--focal)]' => ! $slide->mobile_image_path,
                                    'object-center md:[object-position:var(--focal)]' => $slide->mobile_image_path,
                                ])
                                style="--focal: {{ $slide->focalPosition() }}"
                            >
                        </picture>

                        {{-- Voile en dégradé depuis le texte plutôt qu'un aplat : la
                             photo garde son éclat là où il n'y a rien à lire. Sur
                             téléphone, une photo verticale porte son texte en bas : le
                             voile monte alors du bas, et les visages restent dégagés.
                             Sur grand écran, le texte d'un portrait est déjà sur fond
                             clair : aucun voile, la photo reste nette. --}}
                        @if ($phonePortrait)
                            <div class="absolute inset-0 bg-gradient-to-t from-brand-ink/95 via-brand-ink/50 to-brand-ink/10 sm:bg-none sm:bg-transparent"></div>
                        @endif
                        <div @class([
                            'absolute inset-0 sm:bg-gradient-to-r sm:from-brand-ink/80 sm:via-brand-ink/40 sm:to-brand-ink/5',
                            'bg-brand-ink/45' => ! $phonePortrait,
                            'hidden sm:block' => $phonePortrait,
                            'xl:hidden' => $portrait,
                        ])></div>
                        <div @class([
                            'absolute inset-x-0 bottom-0 hidden h-1/3 bg-gradient-to-t from-brand-ink/50 to-transparent sm:block',
                            'xl:hidden' => $portrait,
                        ])></div>
                    @endif

                    @unless ($slide->image_path)
                        <x-shop.fold-backdrop />
                    @endunless

                    <div @class([
                        'relative h-full max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 flex flex-col items-start gap-6 py-24',
                        'justify-center' => ! $phonePortrait,
                        // Téléphone : texte en bas, les visages restent dégagés en haut.
                        'justify-end pb-16 sm:justify-center sm:pb-24' => $phonePortrait,
                        // Grand écran : texte Encre sur la moitié claire.
                        'xl:text-brand-ink' => $portrait,
                    ])>
                        <span @class(['text-[0.75rem] uppercase tracking-[0.4em] text-brand-surface/60', 'xl:text-brand-muted' => $portrait])>Aissatou Store</span>
                        <h1 @class([
                            'font-display text-4xl sm:text-6xl lg:text-7xl leading-[1.05] max-w-3xl',
                            'hero-portrait-title' => $portrait,
                        ])>{{ $slide->title }}</h1>

                        @if ($slide->subtitle)
                            <p @class(['text-brand-surface/70 max-w-lg text-base sm:text-lg', 'xl:text-brand-ink/70' => $portrait])>{{ $slide->subtitle }}</p>
                        @endif

                        <div class="flex flex-wrap gap-3 mt-2">
                            @if ($slide->cta_label)
                                <x-shop.cta :href="$slide->cta_url ?: route('shop.index')" variant="primary">
                                    {{ $slide->cta_label }}
                                </x-shop.cta>
                            @endif

                            <x-shop.cta
                                :href="route('shop.index', ['nouveautes' => 1])"
                                on-dark
                                :class="$portrait ? 'xl:border-brand-ink/30 xl:text-brand-ink xl:hover:border-brand-ink' : ''"
                            >
                                Explorer les nouveautés
                            </x-shop.cta>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Points de navigation alignés sur le texte. --}}
            @if ($slides->count() > 1)
                <div class="absolute inset-x-0 bottom-6 z-10">
                    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 flex gap-2">
                        @foreach ($slides as $index => $slide)
                            <button
                                type="button"
                                @click="active = {{ $index }}"
                                class="h-1.5 rounded-full transition-all"
                                :class="active === {{ $index }} ? 'w-6 bg-brand-accent' : (portraits[active] ? 'w-1.5 bg-brand-surface/50 xl:bg-brand-ink/25' : 'w-1.5 bg-brand-surface/50')"
                                aria-label="Aller à la diapositive {{ $index + 1 }}"
                            ></button>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </section>

    @include('shop.partials.home-marquee', ['items' => $settings->marquee_items ?? []])

    {{-- NOS UNIVERS — une tuile par catégorie racine (architecture de l'offre,
         rapport d'identité p.3), en mosaïque éditoriale : la première tuile
         occupe deux rangées, le nom est posé sur la photo, et le cadre ouvert
         « Passage Frame » (p.9) s'allonge au survol.

         Le descripteur court de la p.3 (« Le geste quotidien », « La matière et
         le mouvement »…) vient de la description de la catégorie, éditable en
         admin : on n'en garde que la première proposition.

         La mosaïque suppose au moins 5 univers ; en dessous, grille régulière. --}}
    @if ($collections->isNotEmpty())
        @php
            $count = $collections->count();
            $mosaic = $count >= 5;
            // Après la grande tuile et les 4 tuiles qui l'encadrent, les
            // suivantes vont par 3 : la dernière s'élargit pour finir la rangée.
            $tail = $mosaic ? ($count - 5) % 3 : 0;
        @endphp

        <section class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-20 sm:py-28">
            <div class="mb-10 sm:mb-14 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between" data-reveal>
                <div>
                    <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Explorer</span>
                    <h2 class="mt-3 font-display text-4xl sm:text-5xl text-brand-ink leading-[1.05]">Nos univers</h2>
                </div>
                <p class="max-w-sm text-brand-muted lg:text-right">Une même exigence pour chaque univers : moins d'objets, mieux choisis, mieux présentés.</p>
            </div>

            <div @class([
                'grid gap-3 sm:gap-4',
                'grid-cols-2 lg:grid-cols-3 auto-rows-[230px] sm:auto-rows-[300px] lg:auto-rows-[310px]' => $mosaic,
                'grid-cols-2 sm:grid-cols-3 auto-rows-[260px] sm:auto-rows-[340px]' => ! $mosaic,
            ])>
                @foreach ($collections as $index => $collection)
                    @php
                        $descriptor = $collection->description
                            ? Str::limit(rtrim(trim(Str::before(Str::before($collection->description, ' :'), ',')), '.'), 60)
                            : null;
                        $isLead = $mosaic && $index === 0;
                        $isLast = $index === $count - 1;
                        // Mobile (2 colonnes) : la grande tuile prend toute la
                        // largeur ; si le reste est impair, la dernière aussi.
                        $mobileWide = $isLead || ($isLast && ($count - ($mosaic ? 1 : 0)) % 2 === 1);
                    @endphp

                    <a
                        href="{{ route('shop.category', $collection->slug) }}"
                        @class([
                            'group relative block overflow-hidden bg-brand-ink',
                            'col-span-2' => $mobileWide,
                            'lg:col-span-1 lg:row-span-2' => $isLead,
                            'lg:col-span-3' => $mosaic && $isLast && $tail === 1,
                            'lg:col-span-2' => $mosaic && $isLast && $tail === 2,
                            'lg:col-span-1' => $mosaic && $mobileWide && ! $isLead && ! ($isLast && $tail > 0),
                            'sm:col-span-1' => ! $mosaic && $mobileWide,
                        ])
                        data-reveal
                        style="--reveal-delay: {{ min($index, 5) * 90 }}ms"
                    >
                        {{-- Grande version pour les tuiles qui s'étirent (la première, la dernière élargie). --}}
                        @if ($image = $collection->sizedUrl($isLead || $mobileWide ? 'large' : 'medium'))
                            <img
                                src="{{ $image }}"
                                alt=""
                                loading="lazy"
                                class="absolute inset-0 h-full w-full object-cover transition duration-[1400ms] ease-out group-hover:scale-[1.06]"
                                style="object-position: {{ $collection->focalPosition() }}"
                            >
                        @else
                            <x-shop.fold-backdrop :cut="false" />
                        @endif

                        {{-- Voile : Encre en bas pour lire le nom, qui vire au Cassis au survol. --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-brand-ink/90 via-brand-ink/25 to-transparent"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-brand-signature/80 via-brand-signature/10 to-transparent opacity-0 transition duration-700 group-hover:opacity-100"></div>

                        <div class="passage-frame passage-frame--light absolute inset-3 sm:inset-5"></div>

                        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 text-brand-surface">
                            <span class="text-[0.7rem] uppercase tracking-[0.35em] text-brand-surface/60">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 @class([
                                'mt-2 font-display leading-[1.05]',
                                'text-3xl sm:text-4xl lg:text-5xl' => $isLead,
                                'text-2xl sm:text-3xl' => ! $isLead,
                            ])>{{ $collection->name }}</h3>

                            @if ($descriptor)
                                {{-- Toujours visible au doigt ; révélé au survol sur grand écran. --}}
                                <p class="mt-2 max-w-xs text-sm text-brand-surface/80 transition-all duration-500 lg:max-h-0 lg:overflow-hidden lg:opacity-0 lg:group-hover:max-h-16 lg:group-hover:opacity-100">{{ $descriptor }}</p>
                            @endif

                            <span class="mt-4 inline-flex items-center gap-2 text-[0.75rem] uppercase tracking-[0.25em]">
                                Découvrir
                                <svg class="h-3.5 w-3.5 transition-transform duration-500 group-hover:translate-x-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- NOUVEAUTÉS — les produits marqués « Nouveauté » en admin, sur un bandeau
         Parchemin qui rompt le fond de page.

         La section disparaît si rien n'est marqué : mieux vaut ne rien annoncer
         qu'annoncer de fausses nouveautés. --}}
    @if ($newProducts->isNotEmpty())
        <section class="border-y border-brand-ink/5 bg-brand-parchment/60">
            <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-20 sm:py-24">
                <div class="mb-10 flex items-end justify-between gap-4" data-reveal>
                    <div>
                        <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">À découvrir</span>
                        <h2 class="mt-3 font-display text-4xl sm:text-5xl text-brand-ink leading-[1.05]">Nouveautés</h2>
                    </div>

                    <a href="{{ route('shop.index', ['nouveautes' => 1]) }}" class="group inline-flex items-center gap-2 whitespace-nowrap text-xs uppercase tracking-[0.2em] text-brand-ink">
                        <span class="border-b border-brand-ink/30 pb-1 transition group-hover:border-brand-accent">Tout voir</span>
                        <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>

                {{-- Le défilement continu suppose assez de cartes pour que la boucle
                     ne se voie pas : en dessous, grille fixe. --}}
                @if ($newProducts->count() >= 5)
                    <div class="overflow-hidden marquee-viewport" data-reveal>
                        <div class="flex gap-6 w-max marquee-track">
                            @foreach ([1, 2] as $repeat)
                                @foreach ($newProducts as $product)
                                    {{-- 2e passage = copie de boucle : ignorée par les
                                         lecteurs d'écran et la touche Tab. --}}
                                    <div @class(['w-40 sm:w-56 flex-shrink-0', 'marquee-copy' => $repeat === 2]) @if ($repeat === 2) aria-hidden="true" inert @endif>
                                        <x-shop.product-card :product="$product" />
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4" data-reveal>
                        @foreach ($newProducts as $product)
                            <x-shop.product-card :product="$product" />
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    @include('shop.partials.home-look')

    {{-- NOTRE HISTOIRE — le récit de la maison (page À propos), en version courte :
         citation en grand, premier paragraphe, les trois valeurs. Tout vient des
         réglages du site (Filament > Contenu du site > Réglages du site). --}}
    @if ($settings->about_quote || $settings->about_story)
        @php
            $storyExcerpt = $settings->about_story
                ? Str::limit(trim(Str::before($settings->about_story, "\n\n")), 340)
                : null;
        @endphp

        <section class="overflow-hidden">
            <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-20 sm:py-28 grid items-center gap-16 lg:grid-cols-2 lg:gap-24">
                <div class="group relative mx-3 sm:mx-5" data-reveal>
                    <div class="relative aspect-[4/5] overflow-hidden bg-brand-ink">
                        @if ($storyImages)
                            {{-- Diaporama : fondu toutes les 5 s et zoom très lent
                                 sur la photo à l'écran, seulement quand la section
                                 est visible. Chaque photo ne se télécharge qu'au
                                 moment d'arriver (la suivante est préparée en
                                 avance). L'ensemble glisse doucement au défilement
                                 (profondeur) et zoome au survol. Animations
                                 réduites : première photo, fixe. --}}
                            <div
                                class="relative h-full w-full transition duration-[1400ms] ease-out motion-safe:group-hover:scale-[1.04]"
                                x-data="{
                                    shift: 0, current: 0, loaded: [0, 1], count: {{ count($storyImages) }}, visible: false,
                                    show(index) {
                                        this.loaded.push(index, (index + 1) % this.count);
                                        this.current = index;
                                    },
                                }"
                                x-init="
                                    if (! matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                        const update = () => {
                                            const box = $el.getBoundingClientRect();
                                            shift = Math.max(-32, Math.min(32, (innerHeight / 2 - (box.top + box.height / 2)) * 0.08));
                                        };
                                        update();
                                        addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });

                                        if (count > 1) {
                                            new IntersectionObserver(([entry]) => visible = entry.isIntersecting, { threshold: 0.3 }).observe($el);
                                            setInterval(() => visible && show((current + 1) % count), 5000);
                                        }
                                    }
                                "
                            >
                                <div class="relative h-full w-full will-change-transform" :style="`transform: translateY(${shift}px) scale(1.12)`">
                                    @foreach ($storyImages as $index => $url)
                                        <img
                                            @if ($index === 0) src="{{ $url }}" @else x-bind:src="loaded.includes({{ $index }}) ? @js($url) : null" @endif
                                            alt=""
                                            loading="lazy"
                                            class="absolute inset-0 h-full w-full object-cover object-center"
                                            style="transition: opacity 1.2s ease, transform 6s linear"
                                            :class="current === {{ $index }} ? 'opacity-100 motion-safe:scale-[1.08]' : 'opacity-0'"
                                        >
                                    @endforeach
                                </div>

                                @if (count($storyImages) > 1)
                                    <div class="absolute bottom-4 left-4 flex gap-1.5" aria-hidden="true">
                                        @foreach ($storyImages as $index => $url)
                                            <span class="h-0.5 w-5 bg-brand-surface/40 transition duration-500" :class="current === {{ $index }} && '!bg-brand-surface'"></span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <x-shop.fold-backdrop :cut="false" />
                        @endif
                    </div>

                    {{-- Cadre ouvert décalé autour de la photo (Passage Frame, p.9). --}}
                    <div class="passage-frame pointer-events-none absolute -inset-3 sm:-inset-5"></div>

                    {{-- Cartouche Cassis : la promesse de la marque (p.2). --}}
                    <div class="absolute -bottom-8 right-0 sm:-right-6 max-w-[15rem] bg-brand-signature px-6 py-5 text-brand-surface shadow-2xl">
                        <span class="font-display text-4xl leading-none text-brand-surface/40" aria-hidden="true">«</span>
                        <p class="-mt-2 font-display text-lg leading-snug">L'élégance dans la pudeur.</p>
                    </div>
                </div>

                <div data-reveal style="--reveal-delay: 150ms">
                    <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">La maison</span>

                    @if ($settings->about_quote)
                        <blockquote class="mt-5 font-display text-3xl sm:text-4xl lg:text-5xl leading-[1.1] text-brand-ink">
                            « {{ $settings->about_quote }} »
                        </blockquote>
                    @endif

                    @if ($storyExcerpt)
                        <p class="mt-6 max-w-xl leading-relaxed text-brand-muted">{{ $storyExcerpt }}</p>
                    @endif

                    @if (! empty($settings->about_values))
                        <dl class="mt-10 grid gap-6 border-t border-brand-ink/10 pt-8 sm:grid-cols-3">
                            @foreach ($settings->about_values as $valueIndex => $value)
                                <div>
                                    <dt class="flex items-baseline gap-2">
                                        <span class="text-xs font-medium text-brand-accent">{{ str_pad((string) ($valueIndex + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="font-display text-xl text-brand-ink">{{ $value['title'] ?? '' }}</span>
                                    </dt>
                                    <dd class="mt-2 text-sm leading-relaxed text-brand-muted">{{ $value['text'] ?? '' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    <a href="{{ route('shop.about') }}" class="group mt-10 inline-flex items-center gap-3 text-xs uppercase tracking-[0.2em] text-brand-ink">
                        <span class="border-b border-brand-ink/30 pb-1 transition group-hover:border-brand-accent">Découvrir la maison</span>
                        <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </section>
    @endif

    @include('shop.partials.home-gifts')

    {{-- L'EXPÉRIENCE AISSATOU — les indicateurs de confiance (texte éditable :
         Filament > Contenu du site > Réglages du site), présentés comme un
         parcours : un fil Cassis se dessine au défilement et allume chaque
         promesse à son passage. Icônes fixes, assignées par ordre.

         Le symbole de la marque, géant et presque transparent, signe le fond.
         Le bandeau final ouvre une conversation (WhatsApp si le numéro est
         réglé, sinon la page Contact) : un bouton Encre, le Garance restant
         réservé au CTA du hero (règle p.13). --}}
    @php
        $trustIcons = [
            'M2.25 8.25h19.5M2.25 8.25v10.5a1.5 1.5 0 001.5 1.5h16.5a1.5 1.5 0 001.5-1.5V8.25M2.25 8.25l1.72-3.44A1.5 1.5 0 015.31 4h13.38a1.5 1.5 0 011.34.81l1.72 3.44M6 15.75h3',
            'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v11.177m0-11.177L12 4.5m2.25 3.073L18 4.5m-3.75 3.073v11.177',
            'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z',
        ];
        $trustItems = array_values($settings->trust_items ?? []);
    @endphp

    @if (! empty($trustItems))
        <section class="relative overflow-hidden border-y border-brand-ink/10 bg-gradient-to-b from-brand-parchment via-brand-parchment/70 to-brand-surface">
            {{-- Symbole de la marque en filigrane. --}}
            <span
                aria-hidden="true"
                class="brand-symbol pointer-events-none absolute -right-24 top-1/2 w-[34rem] -translate-y-1/2 text-brand-signature/[0.045] sm:-right-10"
                style="--brand-symbol: url('{{ asset('brand/symbole-128.png') }}')"
            ></span>

            <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-20 sm:py-28">
                <div class="mx-auto max-w-2xl text-center" data-reveal>
                    <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">L'expérience Aissatou</span>
                    <h2 class="mt-4 font-display text-4xl sm:text-5xl text-brand-ink leading-[1.05]">Commandez l'esprit tranquille</h2>
                    <p class="mt-5 text-brand-muted">De la commande à votre porte, chaque détail est pensé pour vous simplifier la vie — et vous faire sentir choyée.</p>
                </div>

                <ol @class([
                    'relative mt-16 sm:mt-20 grid gap-14 lg:gap-10',
                    'lg:grid-cols-3' => count($trustItems) >= 3,
                    'lg:grid-cols-2' => count($trustItems) === 2,
                ])>
                    {{-- Le fil : horizontal sur grand écran (d'une pastille à
                         l'autre), vertical sur mobile. Il se dessine au défilement. --}}
                    @if (count($trustItems) > 1)
                        @php $edge = count($trustItems) >= 3 ? '16.666%' : '25%'; @endphp
                        <div aria-hidden="true" data-reveal class="journey-line pointer-events-none absolute top-10 hidden h-px bg-brand-signature/35 lg:block" style="left: {{ $edge }}; right: {{ $edge }}"></div>
                        <div aria-hidden="true" data-reveal class="journey-line journey-line--y pointer-events-none absolute bottom-10 left-10 top-10 w-px bg-brand-signature/35 lg:hidden"></div>
                    @endif

                    @foreach ($trustItems as $index => $text)
                        <li
                            class="journey-step group relative flex items-start gap-6 lg:flex-col lg:items-center lg:gap-0 lg:text-center"
                            data-reveal
                            style="--reveal-delay: {{ 250 + $index * 380 }}ms"
                        >
                            <div class="journey-dot relative z-10 flex h-20 w-20 shrink-0 items-center justify-center rounded-full border border-brand-signature/40 bg-brand-surface text-brand-signature shadow-sm transition duration-500 group-hover:scale-105 group-hover:shadow-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $trustIcons[$index % count($trustIcons)] }}" />
                                </svg>
                            </div>

                            <div class="relative pt-3 lg:pt-10">
                                {{-- Grand numéro en filigrane, centré derrière la promesse
                                     (et non derrière l'étiquette, qu'il brouillait). --}}
                                <span aria-hidden="true" class="pointer-events-none select-none absolute right-0 top-1/2 -translate-y-1/2 font-display text-[7rem] leading-none text-brand-signature/[0.06] lg:left-1/2 lg:right-auto lg:top-[60%] lg:-translate-x-1/2 lg:text-[10rem]">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>

                                <span class="relative text-[0.7rem] uppercase tracking-[0.35em] text-brand-accent">Promesse {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <p class="relative mt-3 max-w-xs font-display text-2xl leading-snug text-brand-ink lg:mx-auto">{{ $text }}</p>
                                <span class="relative mt-5 block h-px w-10 bg-brand-accent/50 transition-all duration-500 group-hover:w-20 lg:mx-auto"></span>
                            </div>
                        </li>
                    @endforeach
                </ol>

                {{-- Bandeau d'invitation : une conseillère, plutôt qu'une FAQ. --}}
                <div class="mt-20 sm:mt-24 flex flex-col items-center gap-6 border-t border-brand-ink/10 pt-12 text-center sm:flex-row sm:justify-between sm:text-left" data-reveal>
                    <div>
                        <p class="font-display text-2xl sm:text-3xl text-brand-ink">Une question avant de commander ?</p>
                        <p class="mt-2 text-sm text-brand-muted">Une conseillère vous répond, avec plaisir — taille, matière, idée cadeau.</p>
                    </div>

                    <a
                        href="{{ $settings->contact_whatsapp ? route('shop.whatsapp') : route('shop.contact') }}"
                        @if ($settings->contact_whatsapp) target="_blank" rel="noopener" data-whatsapp-cta @endif
                        class="group inline-flex shrink-0 items-center gap-3 rounded-sm bg-brand-ink px-8 py-4 text-xs font-medium uppercase tracking-[0.15em] text-brand-surface transition duration-300 hover:bg-brand-signature hover:shadow-xl"
                    >
                        @if ($settings->contact_whatsapp)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                            </svg>
                        @endif
                        Écrire à une conseillère
                        <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- AVIS CLIENTS — section sombre (Encre + fond plissé), note moyenne en
         tête : la preuve sociale vient AVANT l'invitation à laisser un avis. --}}
    @if ($reviews->isNotEmpty())
        <section class="relative overflow-hidden bg-brand-ink text-brand-surface">
            <x-shop.fold-backdrop :cut="false" class="opacity-70" />

            {{-- Guillemet géant décoratif. --}}
            <span aria-hidden="true" class="pointer-events-none select-none absolute -top-16 left-2 sm:left-10 font-display text-[16rem] sm:text-[22rem] leading-none text-brand-surface/[0.05]">“</span>

            <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-20 sm:py-28">
                <div class="mx-auto mb-14 max-w-2xl text-center" data-reveal>
                    <span class="text-xs uppercase tracking-[0.3em] text-brand-sage">Avis clients</span>
                    <h2 class="mt-4 font-display text-4xl sm:text-5xl leading-[1.05]">Elles nous font confiance</h2>

                    @if ($reviewsAverage)
                        <div class="mt-6 inline-flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                            <div class="flex gap-0.5 text-brand-parchment" aria-hidden="true">
                                @for ($i = 1; $i <= 5; $i++)
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="{{ $i <= round($reviewsAverage) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.12 5.11a.56.56 0 00.48.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.19.56l1.29 5.38a.56.56 0 01-.84.61l-4.73-2.88a.56.56 0 00-.58 0l-4.73 2.88a.56.56 0 01-.84-.61l1.29-5.38a.56.56 0 00-.19-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.48-.35L11.48 3.5z" />
                                    </svg>
                                @endfor
                            </div>
                            <span class="font-display text-2xl">{{ number_format($reviewsAverage, 1, ',', ' ') }}</span>
                            <span class="text-sm text-brand-surface/60">/ 5 · {{ $reviewsRated }} avis</span>
                        </div>
                    @endif
                </div>

                <div class="overflow-hidden marquee-viewport" data-reveal>
                    <div class="flex gap-6 w-max marquee-track">
                        @foreach ([1, 2] as $repeat)
                            @foreach ($reviews as $review)
                                <div @class(['w-72 sm:w-96 flex-shrink-0', 'marquee-copy' => $repeat === 2]) @if ($repeat === 2) aria-hidden="true" inert @endif>
                                    <x-shop.review-card :review="$review" dark />
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                <div class="mt-14 text-center" data-reveal>
                    <x-shop.cta href="#avis" on-dark x-on:click="$dispatch('open-review-form')">
                        Laisser un avis
                    </x-shop.cta>
                </div>
            </div>
        </section>
    @endif

    @include('shop.partials.home-journal')

    {{-- FORMULAIRE d'avis — replié derrière un bouton : il ne s'impose plus en
         grand sur l'accueil. Il s'ouvre d'office après un envoi (message de
         merci) ou une erreur de saisie. Avis créé NON publié, modéré en admin.
         Sans JavaScript, le formulaire reste affiché et utilisable. --}}
    <section
        id="avis"
        class="relative scroll-mt-24 bg-brand-surface"
        x-data="{ open: {{ $errors->any() || session('status') === 'review-submitted' ? 'true' : 'false' }}, rating: {{ (int) old('rating', 0) }}, hover: 0 }"
        x-on:open-review-form.window="open = true"
    >
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-20">
            <div class="text-center" data-reveal>
                <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Votre avis compte</span>
                <h2 class="mt-3 font-display text-3xl sm:text-4xl text-brand-ink leading-tight">Partagez votre expérience</h2>
                <p class="mt-3 text-brand-muted">Quelques mots suffisent. Votre avis sera publié après vérification par notre équipe.</p>

                <button
                    type="button"
                    x-show="! open"
                    x-on:click="open = true"
                    style="display: none"
                    class="group mt-8 inline-flex items-center gap-2 rounded-sm border border-brand-ink/30 px-8 py-3 text-sm font-medium uppercase tracking-wide text-brand-ink transition hover:border-brand-ink"
                >
                    Écrire mon avis
                    <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-y-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            </div>

            @if (session('status') === 'review-submitted')
                <p class="mt-8 flex items-center gap-2 rounded-sm border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Merci ! Votre avis a bien été envoyé. Il apparaîtra sur le site après vérification.
                </p>
            @endif

            <form
                method="post"
                action="{{ route('shop.reviews.store') }}"
                x-show="open"
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 -translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-10 space-y-6 rounded-sm border border-brand-ink/10 bg-white/70 backdrop-blur-sm p-6 sm:p-10 shadow-sm"
            >
                @csrf
                <x-honeypot />

                {{-- Notation par étoiles interactive (survol + clic). --}}
                <div>
                    <x-input-label value="Votre note" />
                    <input type="hidden" name="rating" :value="rating || ''">
                    <div class="mt-2 flex items-center gap-1.5" @mouseleave="hover = 0">
                        <template x-for="star in 5" :key="star">
                            <button
                                type="button"
                                @click="rating = (rating === star ? 0 : star)"
                                @mouseenter="hover = star"
                                class="transition-transform duration-150 hover:scale-125 focus:outline-none"
                            >
                                <svg class="h-9 w-9 transition-colors duration-200"
                                     :class="(hover || rating) >= star ? 'text-brand-signature' : 'text-brand-ink/15'"
                                     viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 5.11a.56.56 0 00.48.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.19.56l1.29 5.38a.56.56 0 01-.84.61l-4.73-2.88a.56.56 0 00-.58 0l-4.73 2.88a.56.56 0 01-.84-.61l1.29-5.38a.56.56 0 00-.19-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.48-.35L11.48 3.5z" />
                                </svg>
                            </button>
                        </template>
                        <span class="ml-2 text-sm text-brand-muted" x-text="rating ? rating + '/5' : 'Optionnel'"></span>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('rating')" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="author_name" value="Votre nom" />
                        <x-text-input id="author_name" name="author_name" type="text" class="mt-1 block w-full" :value="old('author_name')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('author_name')" />
                    </div>

                    <div>
                        <x-input-label for="location" value="Ville (optionnel)" />
                        <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" :value="old('location')" />
                        <x-input-error class="mt-2" :messages="$errors->get('location')" />
                    </div>
                </div>

                <div x-data="{ count: {{ mb_strlen(old('comment', '')) }} }">
                    <x-input-label for="comment" value="Votre avis" />
                    <textarea
                        id="comment" name="comment" rows="4" required maxlength="600"
                        @input="count = $event.target.value.length"
                        class="mt-1 block w-full rounded-sm border-brand-ink/20 text-sm transition focus:border-brand-signature focus:ring-brand-accent"
                    >{{ old('comment') }}</textarea>
                    <div class="mt-1 flex items-center justify-between">
                        <x-input-error :messages="$errors->get('comment')" />
                        <span class="ml-auto text-xs text-brand-muted"><span x-text="count">0</span>/600</span>
                    </div>
                </div>

                <button
                    type="submit"
                    class="group inline-flex items-center gap-2 rounded-sm bg-brand-ink px-8 py-3 text-sm font-medium uppercase tracking-wide text-brand-surface transition-all duration-300 hover:bg-brand-accent hover:text-brand-surface hover:shadow-lg"
                >
                    <span>Envoyer mon avis</span>
                    <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </form>
        </div>
    </section>
</x-shop-layout>

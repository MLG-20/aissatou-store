<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        {{-- Aperçu d'un lien partagé (WhatsApp, réseaux sociaux). --}}
        @if ($image)
            <meta property="og:type" content="website">
            <meta property="og:title" content="{{ $title ?? config('app.name') }}">
            @if ($description)
                <meta property="og:description" content="{{ $description }}">
            @endif
            <meta property="og:image" content="{{ url($image) }}">
            <meta property="og:url" content="{{ url()->current() }}">
        @endif

        {{-- Favicon dérivé du symbole de la marque (cf. public/brand/). --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" href="/brand/favicon-32.png" sizes="32x32">
        <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&family=fraunces:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="font-sans antialiased bg-brand-surface text-brand-ink"
        x-data="{ mobileNavOpen: false, cartOpen: {{ session('cart_opened') ? 'true' : 'false' }} }"
        :class="(cartOpen || mobileNavOpen) && 'overflow-hidden'"
    >
        {{-- ARCHITECTURE DE L'OFFRE (rapport d'identité p.3), construite une seule
             fois et partagée par la navigation de bureau ET le tiroir mobile :
             les deux écrans montrent forcément les mêmes entrées, dans le même
             ordre. C'est ce que la duplication précédente ne garantissait pas —
             le mobile avait dérivé vers un accordéon « Catalogue » absent du
             rapport. --}}
        @php
            // Rayon courant, pour marquer l'onglet actif : la catégorie
            // affichée, ou celle du produit consulté (remontée à sa racine).
            $currentCategory = request()->route('category');
            $currentProduct = request()->route('product');
            $currentCategory = $currentCategory instanceof \App\Models\Catalogue\Category
                ? $currentCategory
                : ($currentProduct instanceof \App\Models\Catalogue\Product ? $currentProduct->category : null);
            $currentSlugs = array_filter([$currentCategory?->slug, $currentCategory?->parent?->slug]);

            $navLinks = [[
                'label' => 'Nouveautés',
                'url' => route('shop.index', ['nouveautes' => 1]),
                'children' => [],
                'active' => request()->routeIs('shop.index') && request()->boolean('nouveautes'),
                'editorial' => false,
            ]];

            foreach ($navCategories as $navCategory) {
                $navLinks[] = [
                    'label' => $navCategory['name'],
                    'url' => route('shop.category', $navCategory['slug']),
                    'children' => $navCategory['children'],
                    'active' => in_array($navCategory['slug'], $currentSlugs, true),
                    'editorial' => false,
                ];
            }

            // Le récit de la marque (rapport p.3), distinct de l'offre : séparé
            // des rayons dans l'en-tête et composé autrement.
            $navLinks[] = ['label' => 'Lookbook', 'url' => route('shop.lookbook'), 'children' => [], 'active' => request()->routeIs('shop.lookbook*'), 'editorial' => true];
            $navLinks[] = ['label' => 'Collections', 'url' => route('shop.collections'), 'children' => [], 'active' => request()->routeIs('shop.collection*'), 'editorial' => true];
            $navLinks[] = ['label' => 'Journal', 'url' => route('shop.journal'), 'children' => [], 'active' => request()->routeIs('shop.journal', 'shop.post'), 'editorial' => true];
        @endphp

        <header
            x-data="{ scrolled: false, heroLight: false, wide: false }"
            x-init="scrolled = window.pageYOffset > 20; wide = window.matchMedia('(min-width: 1280px)').matches"
            @scroll.window="scrolled = window.pageYOffset > 20"
            @resize.window="wide = window.matchMedia('(min-width: 1280px)').matches"
            @hero-light.window="heroLight = $event.detail"
            @if ($transparentHeader)
                {{-- Accueil : transparent sur le hero (texte Parchemin), puis fond
                     opaque dès qu'on scrolle OU que le menu mobile s'ouvre — ou
                     quand la slide affichée est un portrait sur grand écran : son
                     texte est sur fond clair, le Parchemin y serait illisible (fond
                     plein, sans flou : la photo dessous reste nette). --}}
                :class="(scrolled || mobileNavOpen)
                    ? 'bg-brand-surface/95 backdrop-blur border-brand-ink/10 text-brand-ink shadow-sm'
                    : (heroLight && wide)
                        ? 'bg-brand-surface border-brand-ink/10 text-brand-ink'
                        : 'bg-transparent border-transparent text-brand-surface'"
                class="fixed top-0 inset-x-0 z-30 border-b transition-colors duration-300"
            @else
                class="sticky top-0 z-30 border-b border-brand-ink/10 bg-brand-surface/95 backdrop-blur text-brand-ink"
            @endif
        >
            @if ($transparentHeader)
                {{-- DÉGRADÉ DE PROTECTION DE LA NAVIGATION.

                     Ahmed refuse un voile assombrissant sur le hero : la
                     photographie doit rester intacte. Mais la cliente garde un
                     carrousel photographique, et sur une image claire les
                     onglets Parchemin disparaissaient purement et simplement —
                     « Hijabs » à « Cadeaux » étaient illisibles. Or le contrôle
                     avant publication du rapport (p.16) impose un contraste
                     texte/fond lisible : il prime.

                     Compromis retenu : pas un voile sur l'image, mais un
                     dégradé confiné à la bande de l'en-tête (h-32), opaque en
                     haut et nul dès le bas de la barre. La composition de la
                     photographie — sujet, horizon, produit — n'est pas touchée,
                     seule la zone que la navigation recouvre déjà est assise.

                     Il disparaît en même temps que la transparence, quand
                     l'en-tête devient opaque au défilement. --}}
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-brand-ink/75 via-brand-ink/40 to-transparent transition-opacity duration-300"
                    x-show="!(scrolled || mobileNavOpen || (heroLight && wide))"
                    x-transition.opacity.duration.300ms
                    aria-hidden="true"
                ></div>
            @endif

            <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-7 xl:px-12">
                {{-- Structure de la maquette (p.10, p.11 et p.12) : LA NAVIGATION
                     EST D'UN SEUL TENANT, alignée sur la marge de gauche, la
                     signature vient après elle, et les icônes ferment à droite.

                     Une version antérieure scindait la navigation de part et
                     d'autre de la signature pour obtenir un logo exactement centré,
                     au motif de l'annotation 01 « Catégories visibles et logo
                     centré ». C'était une sur-lecture : les trois maquettes
                     d'écran dessinent toutes les catégories à gauche et le logo à
                     ~62 % de la largeur, jamais à 50 %. La maquette fait foi.

                     La signature suit donc immédiatement le dernier onglet, comme
                     dans la maquette, et les icônes sont repoussées au bord droit
                     par `ml-auto`. Ajouter une catégorie en admin décale simplement
                     la signature vers la droite, sans jamais couper un onglet. --}}
                {{-- Sous `lg`, la navigation disparaît au profit du burger : on
                     repasse alors en grille à trois colonnes pour que la signature
                     reste centrée entre le burger et les icônes. --}}
                <div class="grid grid-cols-[minmax(0,1fr),auto,minmax(0,1fr)] items-center h-20 gap-2 sm:gap-4 lg:flex lg:justify-start lg:gap-5">
                    <nav class="hidden lg:flex items-center gap-2.5 xl:gap-5 whitespace-nowrap" aria-label="Navigation principale">
                        @foreach ($navLinks as $link)
                            {{-- Filet vertical entre l'offre et le récit de la marque. --}}
                            @if ($link['editorial'] && ! ($navLinks[$loop->index - 1]['editorial'] ?? true))
                                <span class="h-4 w-px bg-current opacity-25" aria-hidden="true"></span>
                            @endif

                            <x-shop.nav-link :link="$link" />
                        @endforeach
                    </nav>

                    {{-- Colonne de gauche sur mobile : le burger, pour que la
                         signature reste centrée sur les petits écrans. Le compte
                         s'y ajoute sous 640 px : se connecter ne doit pas exiger
                         d'ouvrir le menu, et une 4e icône ne tient pas à droite
                         à côté du logo. --}}
                    <div class="lg:hidden flex items-center gap-3 justify-self-start">
                        <button
                            type="button"
                            class="p-2 -ml-2"
                            @click="mobileNavOpen = !mobileNavOpen"
                            aria-label="Ouvrir le menu"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>

                        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="sm:hidden text-current transition hover:text-brand-accent" aria-label="{{ auth()->check() ? 'Mon compte' : 'Se connecter' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </a>
                    </div>

                    <x-shop.signature variant="vertical" :with-slogan="false" class="justify-self-center" />

                    <div class="flex items-center justify-self-end gap-3 sm:gap-5 lg:gap-3.5 xl:gap-5 lg:ml-auto">
                        {{-- Recherche : la maquette montre une loupe ; le champ vit
                             sur le catalogue depuis la sous-étape 3, on y renvoie.
                             Sous 360 px, loupe + cœur + panier ne tiennent plus à
                             côté du logo (largeur minimale 140 px, p.6) : la loupe
                             passe alors dans le menu. --}}
                        <a href="{{ route('shop.index') }}#recherche" class="max-[359px]:hidden text-current transition hover:text-brand-accent" aria-label="Rechercher">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </a>

                        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="hidden sm:inline-block text-current transition hover:text-brand-accent" aria-label="{{ auth()->check() ? 'Mon compte' : 'Se connecter' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </a>

                        {{-- Pas d'icône Contact : depuis l'arrivée du Lookbook, elle
                             faisait déborder l'en-tête de 1 024 à 1 300 px ; le
                             contact reste au pied de page, dans le menu mobile et
                             via la bulle WhatsApp. --}}

                        {{-- Liste d'envies : icône cœur de la maquette (p.10-12), avec
                             compteur comme le panier. Visible aussi sur mobile. --}}
                        <a href="{{ route('shop.wishlist') }}" class="inline-block relative text-current transition hover:text-brand-accent" aria-label="Ma liste d'envies">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                            </svg>
                            @if ($wishlistCount > 0)
                                <span class="absolute -right-2 -top-2 flex h-4 w-4 items-center justify-center bg-brand-accent text-[10px] font-medium text-brand-surface">{{ $wishlistCount }}</span>
                            @endif
                        </a>

                        <button type="button" @click="cartOpen = true" class="relative text-current transition hover:text-brand-accent" aria-label="Ouvrir le panier">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                            </svg>
                            @if ($cartItemCount > 0)
                                <span class="absolute -right-2 -top-2 flex h-4 w-4 items-center justify-center bg-brand-accent text-[10px] font-medium text-brand-surface">{{ $cartItemCount }}</span>
                            @endif
                        </button>
                    </div>
                </div>

            </div>
        </header>

        @include('shop.partials.mobile-nav')
        @include('shop.partials.cart-drawer')

        <main>
            {{ $slot }}
        </main>

        {{-- Pied de page en traitement "bandeau signature" (ébène + or), en
        écho au hero — referme la page sur la même identité forte. Accroche
        et réseaux sociaux éditables depuis Filament > Contenu du site >
        Réglages du site. --}}
        {{-- Pas de marge au-dessus sur le Lookbook : sa dernière section, sombre
             et plein écran, enchaîne directement (sinon une bande claire). --}}
        <footer @class([
            'relative bg-brand-ink text-brand-surface border-t-2 border-brand-signature overflow-hidden',
            'mt-16' => ! request()->routeIs('shop.lookbook*'),
        ])>
            {{-- Filigrane et colonnes superposés dans la MÊME cellule de grille
                 (et non en absolu) : le pied de page prend la hauteur du plus
                 grand des deux, donc le filet du © passe toujours sous « STORE ». --}}
            <div class="grid">
                {{-- Nom de la maison en fond, derrière les colonnes : Fraunces
                     géant à 7 % d'opacité. Purement décoratif (aria-hidden).
                     Crénage coupé : Fraunces resserre la paire « AI », qui collait
                     à cette taille ; espacement régulier à la place. Le retrait
                     gauche de STORE compense l'espacement ajouté après le E, pour
                     rester centré. --}}
                {{-- Sur mobile, les colonnes s'empilent et le pied de page devient
                     haut : le filigrane s'y centre verticalement au lieu de
                     rester collé derrière le logo. --}}
                <div aria-hidden="true" class="pointer-events-none select-none col-start-1 row-start-1 flex flex-col justify-center sm:block sm:pt-10 sm:pb-8 text-center font-display leading-[0.9] text-brand-surface/[0.07] [font-kerning:none]">
                    <span class="block whitespace-nowrap tracking-[0.04em] pl-[0.04em] text-[17vw]">AISSATOU</span>
                    <span class="block whitespace-nowrap tracking-[0.35em] pl-[0.35em] text-[9vw]">STORE</span>
                </div>

                <div class="relative col-start-1 row-start-1 w-full max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 grid grid-cols-2 sm:grid-cols-4 gap-10 content-start">
                    <div class="col-span-2 sm:col-span-1">
                        {{-- Le pied de page est un « espace large » au sens de la p.5 :
                             c'est le verrouillage HORIZONTAL qui s'y applique, slogan
                             compris. Le nom simplement empilé qui figurait ici n'était
                             aucun des deux verrouillages officiels — ni symbole, ni
                             filets encadrant STORE.

                             `-ml-3` compense le padding de protection du composant pour
                             que le signe s'aligne optiquement sur les colonnes de liens ;
                             la zone de protection reste vide, c'est la marge de page. --}}
                        <x-shop.signature variant="horizontal" class="-ml-3 text-brand-surface" />

                        @if ($footerSettings->footer_tagline)
                            <p class="mt-2 text-sm text-brand-surface/60">{{ $footerSettings->footer_tagline }}</p>
                        @endif

                        @if ($footerSettings->social_instagram || $footerSettings->social_facebook || $footerSettings->social_tiktok)
                            {{-- Logos officiels en monochrome (x-shop.social-icon), pastille
                                 qui s'allume au survol. --}}
                            <div class="flex items-center gap-3 mt-6">
                                @foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'] as $network => $label)
                                    @if ($footerSettings->{'social_'.$network})
                                        <a href="{{ $footerSettings->{'social_'.$network} }}" target="_blank" rel="noopener" class="flex h-10 w-10 items-center justify-center rounded-full border border-brand-surface/15 text-brand-surface/70 transition duration-300 hover:-translate-y-0.5 hover:border-brand-signature hover:bg-brand-signature hover:text-brand-surface" aria-label="{{ $label }}">
                                            <x-shop.social-icon :network="$network" class="h-[1.15rem] w-[1.15rem]" />
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-[0.15em] text-brand-sage mb-3">Boutique</p>
                        <ul class="space-y-2 text-sm text-brand-surface/70">
                            <li><a href="{{ route('shop.index') }}" class="hover:text-brand-surface">Catalogue</a></li>
                            @foreach ($navCategories as $navCategory)
                                <li><a href="{{ route('shop.category', $navCategory['slug']) }}" class="hover:text-brand-surface">{{ $navCategory['name'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-[0.15em] text-brand-sage mb-3">La marque</p>
                        <ul class="space-y-2 text-sm text-brand-surface/70">
                            <li><a href="{{ route('shop.about') }}" class="hover:text-brand-surface">À propos</a></li>
                            <li><a href="{{ route('shop.contact') }}" class="hover:text-brand-surface">Contact</a></li>
                        </ul>
                    </div>

                    <div>
                        <p class="text-xs uppercase tracking-[0.15em] text-brand-sage mb-3">Mon compte</p>
                        <ul class="space-y-2 text-sm text-brand-surface/70">
                            @auth
                                <li><a href="{{ route('profile.edit') }}" class="hover:text-brand-surface">Mon compte</a></li>
                            @else
                                <li><a href="{{ route('login') }}" class="hover:text-brand-surface">Connexion</a></li>
                            @endauth
                            <li><a href="{{ route('shop.cart') }}" class="hover:text-brand-surface">Panier</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="relative border-t border-brand-surface/10">
                <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-6 text-sm text-brand-surface/50">
                    &copy; {{ now()->year }} Aissatou Store
                </div>
            </div>
        </footer>

        {{-- WhatsApp flottant : discussion directe, numéro depuis les réglages.
             Il s'efface tant qu'un appel WhatsApp de la page est à l'écran
             (`data-whatsapp-cta`, ex. « Écrire à une conseillère » sur
             l'accueil) : jamais deux boutons WhatsApp côte à côte. Absent du
             Lookbook, plein écran : il y couvrirait l'image (la fin de la page
             et le panneau des pièces proposent déjà WhatsApp). --}}
        @if ($footerSettings->contact_whatsapp && ! request()->routeIs('shop.lookbook*'))
            <a
                href="{{ route('shop.whatsapp') }}"
                target="_blank"
                rel="noopener"
                aria-label="Discuter sur WhatsApp"
                x-data="{ hidden: false }"
                x-init="
                    const ctas = document.querySelectorAll('[data-whatsapp-cta]');
                    if (ctas.length && 'IntersectionObserver' in window) {
                        const visible = new Set();
                        const observer = new IntersectionObserver((entries) => {
                            entries.forEach((e) => e.isIntersecting ? visible.add(e.target) : visible.delete(e.target));
                            hidden = visible.size > 0;
                        });
                        ctas.forEach((cta) => observer.observe(cta));
                    }
                "
                {{-- Masqué aussi sous un tiroir ouvert (menu, panier) : à
                     z-index égal, il passerait par-dessus. --}}
                :class="(hidden || mobileNavOpen || cartOpen) && 'pointer-events-none translate-y-4 opacity-0'"
                :tabindex="(hidden || mobileNavOpen || cartOpen) ? -1 : null"
                class="float-lift fixed bottom-20 right-6 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-brand-ink text-brand-surface shadow-lg transition duration-300 hover:bg-brand-accent"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                </svg>
            </a>
        @endif

        {{-- Retour en haut : apparaît après 600 px de scroll, smooth scroll. --}}
        <button
            type="button"
            x-data="{ visible: false }"
            x-init="window.addEventListener('scroll', () => visible = window.pageYOffset > 600)"
            x-show="visible"
            x-cloak
            x-transition.opacity.duration.300ms
            @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            aria-label="Retour en haut"
            class="float-lift fixed bottom-6 right-6 z-40 flex h-11 w-11 items-center justify-center rounded-sm bg-brand-ink text-brand-surface shadow-lg transition hover:bg-brand-accent"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
            </svg>
        </button>
    </body>
</html>

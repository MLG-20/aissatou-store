<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name') }}</title>

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
            $navLinks = [['label' => 'Nouveautés', 'url' => route('shop.index', ['nouveautes' => 1]), 'children' => []]];

            foreach ($navCategories as $navCategory) {
                $navLinks[] = [
                    'label' => $navCategory['name'],
                    'url' => route('shop.category', $navCategory['slug']),
                    'children' => $navCategory['children'],
                ];
            }

            // Entrées de la maquette dont le module arrive en Phase 2.2 : elles
            // pointent sur une page d'attente, pas sur un lien mort.
            $navLinks[] = ['label' => 'Collections', 'url' => route('shop.collections'), 'children' => []];
            $navLinks[] = ['label' => 'Journal', 'url' => route('shop.journal'), 'children' => []];
        @endphp

        <header
            x-data="{ scrolled: false }"
            x-init="scrolled = window.pageYOffset > 20"
            @scroll.window="scrolled = window.pageYOffset > 20"
            @if ($transparentHeader)
                {{-- Accueil : transparent sur le hero (texte Parchemin), puis fond
                     opaque dès qu'on scrolle OU que le menu mobile s'ouvre. --}}
                :class="(scrolled || mobileNavOpen)
                    ? 'bg-brand-surface/95 backdrop-blur border-brand-ink/10 text-brand-ink shadow-sm'
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
                    x-show="!(scrolled || mobileNavOpen)"
                    x-transition.opacity.duration.300ms
                    aria-hidden="true"
                ></div>
            @endif

            <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
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
                <div class="grid grid-cols-[minmax(0,1fr),auto,minmax(0,1fr)] items-center h-20 gap-4 lg:flex lg:justify-start lg:gap-6">
                    <nav class="hidden lg:flex items-center gap-4 xl:gap-5 whitespace-nowrap text-[0.62rem] xl:text-[0.68rem] uppercase tracking-[0.1em]">
                        @foreach ($navLinks as $link)
                            <x-shop.nav-link :link="$link" />
                        @endforeach
                    </nav>

                    {{-- Colonne de gauche sur mobile : le burger, pour que la
                         signature reste centrée sur les petits écrans. --}}
                    <button
                        type="button"
                        class="lg:hidden p-2 -ml-2 justify-self-start"
                        @click="mobileNavOpen = !mobileNavOpen"
                        aria-label="Ouvrir le menu"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <x-shop.signature variant="vertical" :with-slogan="false" class="justify-self-center" />

                    <div class="flex items-center justify-self-end gap-4 sm:gap-5 lg:ml-auto">
                        {{-- Recherche : la maquette montre une loupe ; le champ vit
                             sur le catalogue depuis la sous-étape 3, on y renvoie. --}}
                        <a href="{{ route('shop.index') }}#recherche" class="text-current transition hover:text-brand-accent" aria-label="Rechercher">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </a>

                        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="hidden sm:inline-block text-current transition hover:text-brand-accent" aria-label="{{ auth()->check() ? 'Mon compte' : 'Se connecter' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </a>

                        {{-- Liste d'envies : icône cœur de la maquette (p.10-12), avec
                             compteur comme le panier. --}}
                        <a href="{{ route('shop.wishlist') }}" class="hidden sm:inline-block relative text-current transition hover:text-brand-accent" aria-label="Ma liste d'envies">
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
        <footer class="mt-16 bg-brand-ink text-brand-surface border-t-2 border-brand-signature">
            <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 grid grid-cols-2 sm:grid-cols-4 gap-10">
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
                        <div class="flex items-center gap-4 mt-5">
                            @if ($footerSettings->social_instagram)
                                <a href="{{ $footerSettings->social_instagram }}" target="_blank" rel="noopener" class="text-brand-surface/60 hover:text-brand-accent transition" aria-label="Instagram">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75h-9a3.75 3.75 0 00-3.75 3.75v9a3.75 3.75 0 003.75 3.75h9a3.75 3.75 0 003.75-3.75v-9a3.75 3.75 0 00-3.75-3.75z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM16.5 7.5h.008v.008h-.008V7.5z" />
                                    </svg>
                                </a>
                            @endif

                            @if ($footerSettings->social_facebook)
                                <a href="{{ $footerSettings->social_facebook }}" target="_blank" rel="noopener" class="text-brand-surface/60 hover:text-brand-accent transition" aria-label="Facebook">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 3H14a4.5 4.5 0 00-4.5 4.5v3H7v3.5h2.5V21H13v-7h2.5l.75-3.5H13v-2c0-.828.672-1.5 1.5-1.5h2.75V3z" />
                                    </svg>
                                </a>
                            @endif

                            @if ($footerSettings->social_tiktok)
                                <a href="{{ $footerSettings->social_tiktok }}" target="_blank" rel="noopener" class="text-brand-surface/60 hover:text-brand-accent transition" aria-label="TikTok">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12a4 4 0 104 4V4a5 5 0 005 5" />
                                    </svg>
                                </a>
                            @endif
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

            <div class="border-t border-brand-surface/10">
                <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-6 text-sm text-brand-surface/50">
                    &copy; {{ now()->year }} Aissatou Store
                </div>
            </div>
        </footer>
    </body>
</html>

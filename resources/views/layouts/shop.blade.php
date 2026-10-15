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

                        {{-- Contact : combiné téléphone + enveloppe (même style de
                             trait que les autres icônes), lien vers la page. --}}
                        <a href="{{ route('shop.contact') }}" class="hidden sm:inline-block text-current transition hover:text-brand-accent" aria-label="Nous contacter">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <g transform="translate(-0.5,5.6) scale(0.65)">
                                    <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                </g>
                                <g transform="translate(10.7,-1.5) scale(0.56)">
                                    <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </g>
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
        <footer class="relative mt-16 bg-brand-ink text-brand-surface border-t-2 border-brand-signature overflow-hidden">
            {{-- Nom de la maison en fond, derrière les colonnes : Fraunces
                 géant à 7 % d'opacité. Purement décoratif (aria-hidden). --}}
            <div aria-hidden="true" class="pointer-events-none select-none absolute inset-x-0 top-10 text-center font-display leading-[0.9] text-brand-surface/[0.07]">
                <span class="block whitespace-nowrap tracking-tight text-[19vw]">AISSATOU</span>
                <span class="block whitespace-nowrap tracking-[0.35em] text-[9vw]">STORE</span>
            </div>

            <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 grid grid-cols-2 sm:grid-cols-4 gap-10">
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
                        <div class="flex items-center gap-5 mt-5">
                            @if ($footerSettings->social_instagram)
                                <a href="{{ $footerSettings->social_instagram }}" target="_blank" rel="noopener" class="text-brand-surface/60 hover:text-brand-accent transition" aria-label="Instagram">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75h-9a3.75 3.75 0 00-3.75 3.75v9a3.75 3.75 0 003.75 3.75h9a3.75 3.75 0 003.75-3.75v-9a3.75 3.75 0 00-3.75-3.75z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM16.5 7.5h.008v.008h-.008V7.5z" />
                                    </svg>
                                </a>
                            @endif

                            @if ($footerSettings->social_facebook)
                                <a href="{{ $footerSettings->social_facebook }}" target="_blank" rel="noopener" class="text-brand-surface/60 hover:text-brand-accent transition" aria-label="Facebook">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 3H14a4.5 4.5 0 00-4.5 4.5v3H7v3.5h2.5V21H13v-7h2.5l.75-3.5H13v-2c0-.828.672-1.5 1.5-1.5h2.75V3z" />
                                    </svg>
                                </a>
                            @endif

                            @if ($footerSettings->social_tiktok)
                                <a href="{{ $footerSettings->social_tiktok }}" target="_blank" rel="noopener" class="text-brand-surface/60 hover:text-brand-accent transition" aria-label="TikTok">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
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

            <div class="relative border-t border-brand-surface/10">
                <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-6 text-sm text-brand-surface/50">
                    &copy; {{ now()->year }} Aissatou Store
                </div>
            </div>
        </footer>

        {{-- WhatsApp flottant : discussion directe, numéro depuis les réglages. --}}
        @if ($footerSettings->contact_whatsapp)
            <a
                href="{{ route('shop.whatsapp') }}"
                target="_blank"
                rel="noopener"
                aria-label="Discuter sur WhatsApp"
                class="fixed bottom-20 right-6 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-brand-ink text-brand-surface shadow-lg transition hover:bg-brand-accent"
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
            class="fixed bottom-6 right-6 z-40 flex h-11 w-11 items-center justify-center rounded-sm bg-brand-ink text-brand-surface shadow-lg transition hover:bg-brand-accent"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
            </svg>
        </button>
    </body>
</html>

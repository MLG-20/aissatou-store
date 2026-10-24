{{-- TIROIR DE NAVIGATION MOBILE — s'ouvre de la gauche vers la droite via
     l'état Alpine `mobileNavOpen` (défini sur <body>, comme `cartOpen`).

     Il remplace le panneau qui se dépliait sous l'en-tête et qui n'offrait que
     « Accueil / Catalogue / À propos / Contact » : sur mobile, les catégories de
     la maquette — Hijabs, Foulards, Cols, Parfums, Cadeaux — étaient enterrées
     derrière un accordéon « Catalogue » absent du rapport. Le tiroir présente
     désormais EXACTEMENT les mêmes entrées que la navigation de bureau, dans le
     même ordre : c'est la même architecture d'offre (p.3), quel que soit l'écran.

     Le panneau reprend le traitement du hero et du pied de page — Encre profonde
     et fond du pli (p.9/p.10) — plutôt qu'un aplat blanc : la marque reste
     présente jusque dans un menu utilitaire.

     Le tiroir panier s'ouvre depuis la droite ; celui-ci depuis la gauche, du
     côté du bouton qui le déclenche. Les deux directions restent cohérentes avec
     l'objet qu'elles portent. --}}
<div
    x-show="mobileNavOpen"
    x-cloak
    class="fixed inset-0 z-40 lg:hidden"
    @keydown.escape.window="mobileNavOpen = false"
    role="dialog"
    aria-modal="true"
    aria-label="Menu principal"
>
    {{-- Voile sombre : même durée et même courbe que le tiroir panier. --}}
    <div
        x-show="mobileNavOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0 bg-brand-ink/60 backdrop-blur-sm"
        @click="mobileNavOpen = false"
    ></div>

    <div
        x-show="mobileNavOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="absolute left-0 top-0 flex h-full w-[86%] max-w-sm flex-col overflow-hidden bg-brand-ink text-brand-surface shadow-2xl"
    >
        <x-shop.fold-backdrop :cut="false" />

        {{-- Filet Garance sur l'arête du tiroir : l'« Edit Cut » du langage
             graphique (p.9), qui marque l'incision plutôt qu'un bord neutre. --}}
        <div class="absolute inset-y-0 right-0 w-px bg-brand-accent/60"></div>

        <div class="relative flex items-center justify-between px-5 py-5">
            <x-shop.signature variant="horizontal" :with-slogan="false" class="-ml-3" />

            <button
                type="button"
                @click="mobileNavOpen = false"
                class="p-2 text-brand-surface/60 transition hover:text-brand-surface"
                aria-label="Fermer le menu"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="relative flex-1 overflow-y-auto px-5 pb-6">
            @foreach ($navLinks as $index => $link)
                {{-- Entrée en cascade : chaque onglet arrive avec un léger retard
                     sur le précédent, ce qui donne au tiroir sa lecture de haut en
                     bas. Le retard est remis à zéro à la fermeture, sans quoi le
                     menu mettrait une seconde à disparaître. --}}
                <div
                    class="border-b border-brand-surface/10 transition duration-500 ease-out"
                    :class="mobileNavOpen ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-6'"
                    :style="mobileNavOpen ? 'transition-delay: {{ 80 + $index * 45 }}ms' : 'transition-delay: 0ms'"
                >
                    @if ($link['children'])
                        <div x-data="{ open: false }">
                            <div class="flex items-center">
                                {{-- Onglet actif : Garance, comme le filet de l'en-tête. --}}
                                <a href="{{ $link['url'] }}" @class(['flex-1 py-4 font-display text-2xl transition hover:text-brand-accent', 'text-brand-accent' => $link['active'], 'text-brand-surface' => ! $link['active']]) @if ($link['active']) aria-current="page" @endif>
                                    {{ $link['label'] }}
                                </a>

                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="p-3 text-brand-surface/50 transition hover:text-brand-surface"
                                    :aria-expanded="open"
                                    aria-label="Afficher les sous-catégories de {{ $link['label'] }}"
                                >
                                    <svg class="h-4 w-4 transition-transform duration-200" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Fondu simple plutôt que `x-collapse` : le plugin
                                 Alpine correspondant n'est pas installé, et
                                 l'ajouter pour un seul accordéon alourdirait le
                                 bundle de toutes les pages. --}}
                            <div x-show="open" x-cloak x-transition.opacity.duration.200ms class="pb-4 pl-4">
                                @foreach ($link['children'] as $child)
                                    <a href="{{ route('shop.category', $child['slug']) }}" class="block py-2 text-sm text-brand-surface/70 transition hover:text-brand-surface">
                                        {{ $child['name'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ $link['url'] }}" @class(['block py-4 font-display text-2xl transition hover:text-brand-accent', 'italic' => $link['editorial'], 'text-brand-accent' => $link['active'], 'text-brand-surface' => ! $link['active']]) @if ($link['active']) aria-current="page" @endif>
                            {{ $link['label'] }}
                        </a>
                    @endif
                </div>
            @endforeach

            {{-- Pages de marque : volontairement en retrait typographique, elles ne
                 sont pas au même niveau que l'architecture de l'offre. --}}
            <div
                class="mt-6 flex flex-col gap-3 text-xs uppercase tracking-[0.18em] text-brand-surface/60 transition duration-500 ease-out"
                :class="mobileNavOpen ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-6'"
                :style="mobileNavOpen ? 'transition-delay: {{ 80 + count($navLinks) * 45 }}ms' : 'transition-delay: 0ms'"
            >
                <a href="{{ route('shop.about') }}" class="transition hover:text-brand-surface">À propos</a>
                <a href="{{ route('shop.contact') }}" class="transition hover:text-brand-surface">Contact</a>
                <a href="{{ route('shop.wishlist') }}" class="transition hover:text-brand-surface">Liste d'envies</a>
                {{-- Relais de la loupe, masquée dans la barre sous 360 px. --}}
                <a href="{{ route('shop.index') }}#recherche" class="min-[360px]:hidden transition hover:text-brand-surface">Rechercher</a>
            </div>
        </nav>

        {{-- Compte et panier : masqués dans la barre du haut sur petit écran, ils
             doivent rester atteignables ici. --}}
        <div class="relative border-t border-brand-surface/10 bg-brand-ink/40 px-5 py-4">
            <div class="flex items-center justify-between gap-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm uppercase tracking-[0.15em] text-brand-surface/80 transition hover:text-brand-surface">
                        Mon compte
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-sm uppercase tracking-[0.15em] text-brand-surface/80 transition hover:text-brand-surface">
                        Connexion
                    </a>
                @endauth

                <button
                    type="button"
                    @click="mobileNavOpen = false; cartOpen = true"
                    class="flex items-center gap-2 text-sm uppercase tracking-[0.15em] text-brand-surface/80 transition hover:text-brand-surface"
                >
                    Panier
                    @if ($cartItemCount > 0)
                        <span class="flex h-5 min-w-[1.25rem] items-center justify-center bg-brand-accent px-1 text-[10px] font-medium text-brand-surface">
                            {{ $cartItemCount }}
                        </span>
                    @endif
                </button>
            </div>
        </div>
    </div>
</div>

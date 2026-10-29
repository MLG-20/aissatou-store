{{-- Cadre de l'espace cliente (App\View\Components\AccountLayout). --}}
@php
    $menu = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Tableau de bord', 'badge' => null,
            'icon' => ['M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25']],
        ['route' => 'account.orders.index', 'match' => 'account.orders.*', 'label' => 'Mes commandes', 'badge' => $counts['active'] ?: null,
            'icon' => ['M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z']],
        ['route' => 'shop.wishlist', 'match' => 'shop.wishlist', 'label' => 'Mes coups de cœur', 'badge' => null,
            'icon' => ['M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z']],
        ['route' => 'account.addresses.index', 'match' => 'account.addresses.*', 'label' => 'Mes adresses', 'badge' => null,
            'icon' => ['M15 10.5a3 3 0 11-6 0 3 3 0 016 0z', 'M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z']],
        ['route' => 'profile.edit', 'match' => 'profile.*', 'label' => 'Mes informations', 'badge' => null,
            'icon' => ['M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z']],
    ];

    $stats = [
        ['value' => $counts['orders'], 'label' => $counts['orders'] > 1 ? 'commandes' : 'commande'],
        ['value' => $counts['wishlist'], 'label' => $counts['wishlist'] > 1 ? 'coups de cœur' : 'coup de cœur'],
        ['value' => $counts['addresses'], 'label' => $counts['addresses'] > 1 ? 'adresses' : 'adresse'],
    ];
@endphp

<x-shop-layout :title="($title ?? 'Mon espace').' — '.config('app.name')">
    {{-- EN-TÊTE PERSONNEL : fond Encre plissé, monogramme dans le cadre ouvert
         de la charte (Passage Frame, p.9), trois chiffres à elle. --}}
    <section class="relative overflow-hidden bg-brand-ink text-brand-surface">
        <x-shop.fold-backdrop :cut="false" class="opacity-80" />

        <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-12 sm:py-16">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex items-center gap-5 sm:gap-7">
                    <div class="relative shrink-0 p-2">
                        <div class="passage-frame passage-frame--light absolute inset-0"></div>
                        <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center bg-brand-signature font-display text-3xl sm:text-4xl text-brand-surface">
                            {{ Str::upper(Str::substr($firstName, 0, 1)) }}
                        </div>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-xs uppercase tracking-[0.3em] text-brand-sage">Votre espace</span>
                            @if ($loyal)
                                <span class="inline-flex items-center gap-1.5 border border-brand-parchment/40 px-2.5 py-1 text-[0.7rem] uppercase tracking-[0.2em] text-brand-parchment">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M11.48 3.5a.56.56 0 011.04 0l2.12 5.11a.56.56 0 00.48.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.19.56l1.29 5.38a.56.56 0 01-.84.61l-4.73-2.88a.56.56 0 00-.58 0l-4.73 2.88a.56.56 0 01-.84-.61l1.29-5.38a.56.56 0 00-.19-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.48-.35L11.48 3.5z" />
                                    </svg>
                                    Cliente fidèle
                                </span>
                            @endif
                        </div>
                        {{-- Salutation à l'heure de l'appareil de la cliente (diaspora
                             comprise) ; le serveur fournit celle de Dakar par défaut,
                             affichée sans JavaScript. Mêmes bornes des deux côtés. --}}
                        <h1 class="mt-2 font-display text-3xl sm:text-5xl leading-tight">
                            <span x-data x-text="(h => h >= 18 || h < 5 ? 'Bonsoir' : 'Bonjour')(new Date().getHours())">{{ $greeting }}</span> {{ $firstName }}
                        </h1>
                        <p class="mt-2 text-sm text-brand-surface/60">Membre depuis {{ $user->created_at?->translatedFormat('F Y') }}</p>
                    </div>
                </div>

                <dl class="grid grid-cols-3 divide-x divide-brand-surface/10 border-y border-brand-surface/10 lg:border-y-0">
                    @foreach ($stats as $stat)
                        <div class="px-4 py-4 text-center sm:px-8 lg:py-0">
                            <dt class="sr-only">{{ $stat['label'] }}</dt>
                            <dd>
                                <span class="block font-display text-3xl sm:text-4xl">{{ $stat['value'] }}</span>
                                <span class="mt-1 block text-[0.75rem] uppercase tracking-[0.2em] text-brand-surface/60">{{ $stat['label'] }}</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </section>

    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8 sm:py-12">
        <div class="grid gap-8 lg:grid-cols-[15rem,minmax(0,1fr)] lg:gap-12">
            {{-- MENU DE L'ESPACE : colonne sur grand écran ; sur mobile, menu
                 déroulant (bouton = page en cours, avec le badge des commandes
                 en cours pour qu'il ne passe pas inaperçu). Déconnexion ici :
                 la boutique n'en proposait nulle part. --}}
            @php
                $currentItem = collect($menu)->first(fn ($item) => request()->routeIs($item['match'])) ?? $menu[0];
            @endphp

            <nav
                aria-label="Mon espace"
                class="relative lg:self-start lg:sticky lg:top-28"
                x-data="{ open: false }"
                @keydown.escape="open = false"
                @click.outside="open = false"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 border border-brand-ink/15 bg-white px-4 py-3.5 text-left text-sm text-brand-ink shadow-sm lg:hidden"
                    @click="open = ! open"
                    :aria-expanded="open.toString()"
                    aria-expanded="false"
                    aria-controls="account-menu"
                >
                    <svg class="h-5 w-5 shrink-0 text-brand-signature" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        @foreach ($currentItem['icon'] as $path)
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                        @endforeach
                    </svg>
                    <span class="flex-1">
                        <span class="block text-[0.7rem] uppercase tracking-[0.25em] text-brand-muted">Mon espace</span>
                        <span class="block font-display text-base">{{ $currentItem['label'] }}</span>
                    </span>
                    @if ($counts['active'] > 0)
                        <span class="flex h-5 min-w-5 items-center justify-center bg-brand-accent px-1.5 text-[0.75rem] font-medium text-brand-surface" title="{{ $counts['active'] }} commande(s) en cours">{{ $counts['active'] }}</span>
                    @endif
                    <svg class="h-4 w-4 shrink-0 text-brand-ink/50 transition-transform duration-300" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <ul
                    id="account-menu"
                    class="absolute inset-x-0 top-full z-30 mt-2 hidden flex-col border border-brand-ink/10 bg-white py-2 shadow-[0_18px_40px_-12px_rgba(23,21,27,0.35)] lg:static lg:mt-0 lg:flex lg:gap-1 lg:border-0 lg:bg-transparent lg:py-0 lg:shadow-none"
                    :class="open && '!flex'"
                    x-transition.opacity
                >
                    @foreach ($menu as $item)
                        @php $current = request()->routeIs($item['match']); @endphp
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                @class([
                                    'group flex items-center gap-3 whitespace-nowrap border-l-2 px-4 py-3 text-sm transition',
                                    'border-brand-signature bg-brand-parchment/60 text-brand-signature' => $current,
                                    'border-transparent text-brand-ink/70 hover:bg-brand-parchment/40 hover:text-brand-ink' => ! $current,
                                ])
                                @if ($current) aria-current="page" @endif
                            >
                                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    @foreach ($item['icon'] as $path)
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}" />
                                    @endforeach
                                </svg>
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if ($item['badge'])
                                    <span class="flex h-5 min-w-5 items-center justify-center bg-brand-accent px-1.5 text-[0.75rem] font-medium text-brand-surface" title="{{ $item['badge'] }} en cours">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach

                    <li class="mt-2 border-t border-brand-ink/10 pt-2 lg:mt-4 lg:pt-4">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 whitespace-nowrap border-l-2 border-transparent px-4 py-3 text-sm text-brand-muted transition hover:text-brand-accent">
                                <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                </svg>
                                Se déconnecter
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>

            <div class="min-w-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-shop-layout>

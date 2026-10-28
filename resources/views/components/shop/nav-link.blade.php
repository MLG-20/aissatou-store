@props(['link'])

{{-- Entrée de la navigation principale (en-tête de bureau).

     Deux compositions :
     · les rayons (Nouveautés, catégories) : capitales espacées ;
     · le récit de la marque (Collections, Journal) : Fraunces italique en bas
       de casse — l'offre et le récit se distinguent au premier coup d'œil.

     Survol et onglet actif : filet Garance qui s'étire depuis le centre, l'« Edit
     Cut » du langage graphique (p.9). Pas d'`opacity-80` au repos : sur le hero
     photographique, l'onglet Parchemin passait sous le seuil de lisibilité
     (contrôle p.16). --}}
@php
    $active = $link['active'] ?? false;
    $editorial = $link['editorial'] ?? false;

    $labelClasses = $editorial
        ? 'font-display italic normal-case tracking-normal text-[0.85rem] xl:text-base'
        : 'uppercase font-medium text-[0.62rem] xl:text-[0.7rem] tracking-[0.09em] xl:tracking-[0.16em]';

    $underline = 'pointer-events-none absolute inset-x-0 -bottom-0.5 h-px origin-center bg-brand-accent transition-transform duration-500 ease-out '
        .($active ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100 group-focus-visible:scale-x-100');
@endphp

@if ($link['children'])
    <div
        class="relative"
        x-data="{ open: false }"
        @mouseenter="open = true"
        @mouseleave="open = false"
        @keydown.escape="open = false"
        @focusout="$el.contains($event.relatedTarget) || (open = false)"
    >
        <div class="flex items-center gap-1">
            <a
                href="{{ $link['url'] }}"
                @class(['group relative py-1 text-current transition focus:outline-none', $labelClasses])
                @if ($active) aria-current="page" @endif
            >
                {{ $link['label'] }}
                <span class="{{ $underline }}"></span>
            </a>

            <button
                type="button"
                class="p-0.5 text-current opacity-60 transition hover:opacity-100 focus:outline-none focus-visible:opacity-100"
                @click="open = ! open"
                :aria-expanded="open.toString()"
                aria-expanded="false"
                aria-label="Sous-catégories de {{ $link['label'] }}"
            >
                <svg class="h-3 w-3 transition-transform duration-300" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
            </button>
        </div>

        {{-- Sous-menu : Parchemin éclairci, filet Cassis en tête, noms en
             Fraunces. `pt-3` crée un pont invisible sous l'onglet, pour que la
             souris puisse descendre sans refermer le menu. --}}
        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute left-1/2 top-full z-40 -translate-x-1/2 pt-3"
        >
            <div class="w-60 border-t-2 border-brand-signature bg-brand-surface py-3 text-brand-ink normal-case tracking-normal shadow-[0_18px_40px_-12px_rgba(23,21,27,0.35)]">
                @foreach ($link['children'] as $child)
                    <a
                        href="{{ route('shop.category', $child['slug']) }}"
                        class="group/item flex items-center justify-between px-5 py-2.5 font-display text-base text-brand-ink/80 transition hover:bg-brand-parchment/60 hover:text-brand-signature focus:bg-brand-parchment/60 focus:outline-none"
                    >
                        {{ $child['name'] }}
                        <svg class="h-3.5 w-3.5 -translate-x-1 opacity-0 transition duration-300 group-hover/item:translate-x-0 group-hover/item:opacity-100" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                @endforeach

                <a href="{{ $link['url'] }}" class="mx-5 mt-2 block border-t border-brand-ink/10 pt-3 text-[0.65rem] font-medium uppercase tracking-[0.2em] text-brand-muted transition hover:text-brand-signature">
                    Tout l'univers {{ $link['label'] }} →
                </a>
            </div>
        </div>
    </div>
@else
    <a
        href="{{ $link['url'] }}"
        @class(['group relative py-1 text-current transition focus:outline-none', $labelClasses])
        @if ($active) aria-current="page" @endif
    >
        {{ $link['label'] }}
        <span class="{{ $underline }}"></span>
    </a>
@endif

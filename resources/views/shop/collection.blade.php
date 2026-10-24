@php
    $collectionImage = $collection->sizedUrl('large');
    $number = str_pad((string) $chapter, 2, '0', STR_PAD_LEFT);
@endphp

<x-shop-layout :title="$collection->name.' — '.config('app.name')">
    {{-- OUVERTURE DU CHAPITRE : photo nette d'un côté, récit de l'autre (et non
         le texte posé sur une photo assombrie). --}}
    <section class="relative overflow-hidden border-b border-brand-ink/10">
        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-12 sm:py-16 grid items-center gap-12 lg:grid-cols-[minmax(0,1fr),minmax(0,1.1fr)] lg:gap-20">
            <div data-reveal>
                <a href="{{ route('shop.collections') }}" class="group inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-brand-muted transition hover:text-brand-signature">
                    <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:-translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Toutes les collections
                </a>

                <span class="mt-10 block text-xs uppercase tracking-[0.3em] text-brand-signature">Chapitre {{ $number }}</span>
                <h1 class="mt-4 font-display text-5xl sm:text-7xl leading-[1] text-brand-ink">{{ $collection->name }}</h1>

                @if ($collection->description)
                    <p class="mt-6 max-w-lg text-lg leading-relaxed text-brand-muted">{{ $collection->description }}</p>
                @endif

                <p class="mt-8 inline-flex items-center gap-3 border-t border-brand-ink/10 pt-6 text-sm text-brand-ink">
                    <span class="font-display text-3xl">{{ $collection->products_count }}</span>
                    <span class="text-xs uppercase tracking-[0.2em] text-brand-muted">{{ $collection->products_count > 1 ? 'pièces choisies' : 'pièce choisie' }}</span>
                </p>
            </div>

            <div class="relative mx-3 sm:mx-5" data-reveal style="--reveal-delay: 150ms">
                <div class="relative aspect-[5/4] overflow-hidden bg-brand-parchment">
                    @if ($collectionImage)
                        <img src="{{ $collectionImage }}" alt="{{ $collection->name }}" class="h-full w-full object-cover object-center">
                    @else
                        <x-shop.fold-backdrop :cut="false" />
                    @endif
                </div>
                <div class="passage-frame pointer-events-none absolute -inset-3 sm:-inset-5"></div>
                <span aria-hidden="true" class="pointer-events-none select-none absolute -bottom-10 -left-2 sm:-left-8 font-display text-[7rem] sm:text-[9rem] leading-none text-brand-signature/15">{{ $number }}</span>
            </div>
        </div>
    </section>

    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-12">
        <x-shop.filter-bar :action="route('shop.collection', $collection)" :materials="$materials">
            @include('shop.partials.product-grid', ['products' => $products])
        </x-shop.filter-bar>
    </div>

    {{-- POURSUIVRE LA LECTURE : les autres chapitres. --}}
    @if ($others->isNotEmpty())
        {{-- `-mb-16` annule la marge haute du pied de page : le bandeau
             Parchemin le rejoint sans bande crème entre les deux. --}}
        <section class="-mb-16 border-t border-brand-ink/10 bg-brand-parchment/60">
            <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-20">
                <div class="mb-10 flex items-end justify-between gap-4" data-reveal>
                    <div>
                        <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Poursuivre la lecture</span>
                        <h2 class="mt-3 font-display text-3xl sm:text-4xl text-brand-ink">Les autres collections</h2>
                    </div>
                    <a href="{{ route('shop.collections') }}" class="whitespace-nowrap text-xs uppercase tracking-[0.2em] text-brand-ink/70 transition hover:text-brand-signature">Tout le sommaire →</a>
                </div>

                <div class="grid gap-6 sm:grid-cols-3">
                    @foreach ($others as $other)
                        <a href="{{ route('shop.collection', $other) }}" class="group relative block aspect-[4/5] overflow-hidden bg-brand-ink" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                            @if ($otherImage = $other->sizedUrl('medium'))
                                <img src="{{ $otherImage }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-[1400ms] ease-out group-hover:scale-[1.06]">
                            @else
                                <x-shop.fold-backdrop :cut="false" />
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-brand-ink/90 via-brand-ink/20 to-transparent"></div>
                            <div class="passage-frame passage-frame--light absolute inset-3 sm:inset-4"></div>
                            <div class="absolute inset-x-0 bottom-0 p-6 text-brand-surface">
                                <span class="text-[0.6rem] uppercase tracking-[0.3em] text-brand-surface/60">{{ $other->products_count }} {{ $other->products_count > 1 ? 'pièces' : 'pièce' }}</span>
                                <h3 class="mt-2 font-display text-2xl">{{ $other->name }}</h3>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-shop-layout>

<x-shop-layout :title="'Collections — '.config('app.name')">
    {{-- COLLECTIONS — le récit éditorial de la maison (rapport p.3), mis en page
         comme un magazine : une ouverture avec sommaire, puis un chapitre par
         collection. Photo nette (l'image domine, p.11), texte à côté et non
         par-dessus, quelques vraies pièces en aperçu. Les chapitres alternent
         côté et fond ; le troisième passe sur fond Encre pour le rythme. --}}

    <section class="relative overflow-hidden border-b border-brand-ink/10">
        <span aria-hidden="true" class="pointer-events-none select-none absolute -right-6 -top-10 font-display text-[12rem] sm:text-[18rem] leading-none text-brand-signature/[0.04]">{{ str_pad((string) $collections->count(), 2, '0', STR_PAD_LEFT) }}</span>

        <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-24 grid gap-12 lg:grid-cols-[minmax(0,1fr),22rem] lg:items-end">
            <div data-reveal>
                <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Le récit éditorial</span>
                <h1 class="mt-4 font-display text-5xl sm:text-7xl leading-[1] text-brand-ink">Collections</h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-brand-muted">Des ensembles choisis par univers — une même collection traverse hijabs, foulards, parfums et objets à offrir.</p>
            </div>

            @if ($collections->isNotEmpty())
                {{-- Sommaire : un lien par chapitre. --}}
                <nav aria-label="Sommaire des collections" data-reveal style="--reveal-delay: 150ms">
                    <p class="mb-4 text-[0.65rem] uppercase tracking-[0.3em] text-brand-muted">Sommaire</p>
                    <ol class="border-t border-brand-ink/10">
                        @foreach ($collections as $collection)
                            <li>
                                <a href="#chapitre-{{ $loop->iteration }}" class="group flex items-baseline gap-4 border-b border-brand-ink/10 py-3 transition hover:pl-2">
                                    <span class="text-xs font-medium text-brand-accent">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="flex-1 font-display text-lg text-brand-ink transition group-hover:text-brand-signature">{{ $collection->name }}</span>
                                    <span class="text-xs text-brand-muted">{{ $collection->products_count }} {{ $collection->products_count > 1 ? 'pièces' : 'pièce' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif
        </div>
    </section>

    @if ($collections->isEmpty())
        <p class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 text-brand-muted">Nos collections arrivent bientôt.</p>
    @else
        @foreach ($collections as $collection)
            @php
                $number = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
                $image = $collection->sizedUrl('large');
                $imageRight = $loop->even;
                $dark = $loop->iteration % 3 === 0;
                // Fonds alternés comptés depuis la fin : le dernier chapitre est
                // toujours crème et se fond dans la marge du pied de page.
                $tone = $dark ? 'bg-brand-ink text-brand-surface' : ($loop->remaining % 2 === 1 ? 'bg-brand-parchment/60' : 'bg-brand-surface');
            @endphp

            <section id="chapitre-{{ $loop->iteration }}" class="relative scroll-mt-24 overflow-hidden {{ $tone }}">
                @if ($dark)
                    <x-shop.fold-backdrop :cut="false" class="opacity-70" />
                @endif

                <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-20 sm:py-28 grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                    {{-- Photo nette, cadre ouvert décalé, grand numéro en filigrane. --}}
                    <a href="{{ route('shop.collection', $collection) }}" @class(['group relative mx-3 block sm:mx-5', 'lg:order-2' => $imageRight]) data-reveal>
                        <div class="relative aspect-[4/5] overflow-hidden {{ $dark ? 'bg-brand-signature/30' : 'bg-brand-parchment' }}">
                            @if ($image)
                                <img src="{{ $image }}" alt="{{ $collection->name }}" loading="lazy" class="h-full w-full object-cover object-center transition duration-[1400ms] ease-out group-hover:scale-[1.05]">
                            @else
                                <x-shop.fold-backdrop :cut="false" />
                            @endif
                        </div>
                        <div @class(['passage-frame pointer-events-none absolute -inset-3 sm:-inset-5', 'passage-frame--light' => $dark])></div>
                        <span aria-hidden="true" @class([
                            'pointer-events-none select-none absolute -bottom-10 font-display text-[7rem] sm:text-[9rem] leading-none',
                            '-right-2 sm:-right-8' => ! $imageRight,
                            '-left-2 sm:-left-8' => $imageRight,
                            'text-brand-surface/15' => $dark,
                            'text-brand-signature/15' => ! $dark,
                        ])>{{ $number }}</span>
                    </a>

                    <div data-reveal style="--reveal-delay: 150ms">
                        <span @class(['text-xs uppercase tracking-[0.3em]', 'text-brand-sage' => $dark, 'text-brand-signature' => ! $dark])>Chapitre {{ $number }}</span>
                        <h2 class="mt-4 font-display text-4xl sm:text-6xl leading-[1.02]">{{ $collection->name }}</h2>

                        @if ($collection->description)
                            <p @class(['mt-6 max-w-lg text-lg leading-relaxed', 'text-brand-surface/75' => $dark, 'text-brand-muted' => ! $dark])>{{ $collection->description }}</p>
                        @endif

                        {{-- Aperçu : quelques vraies pièces de la collection. --}}
                        @if ($collection->products->isNotEmpty())
                            <div class="mt-10 flex gap-3">
                                @foreach ($collection->products as $product)
                                    @php $thumb = $product->primaryImage()?->sizedUrl('thumb'); @endphp
                                    <a href="{{ route('shop.product', $product) }}" class="group/piece relative block w-20 sm:w-24 shrink-0" title="{{ $product->name }}">
                                        <div class="aspect-[3/4] overflow-hidden {{ $dark ? 'bg-brand-surface/10' : 'bg-brand-parchment' }}">
                                            @if ($thumb)
                                                <img src="{{ $thumb }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover/piece:scale-105">
                                            @else
                                                <x-shop.image-placeholder :category="null" />
                                            @endif
                                        </div>
                                    </a>
                                @endforeach

                                @if ($collection->products_count > $collection->products->count())
                                    <a href="{{ route('shop.collection', $collection) }}" @class([
                                        'flex w-20 sm:w-24 shrink-0 aspect-[3/4] items-center justify-center border font-display text-lg transition',
                                        'border-brand-surface/20 text-brand-surface/70 hover:border-brand-surface/50' => $dark,
                                        'border-brand-ink/15 text-brand-muted hover:border-brand-signature hover:text-brand-signature' => ! $dark,
                                    ])>+{{ $collection->products_count - $collection->products->count() }}</a>
                                @endif
                            </div>
                        @endif

                        <a href="{{ route('shop.collection', $collection) }}" @class([
                            'group mt-10 inline-flex items-center gap-3 px-8 py-4 text-xs font-medium uppercase tracking-[0.15em] transition duration-300',
                            'bg-brand-surface text-brand-ink hover:bg-brand-parchment' => $dark,
                            'bg-brand-ink text-brand-surface hover:bg-brand-signature' => ! $dark,
                        ])>
                            Découvrir la collection
                            {{-- Le nombre de pièces est déjà au sommaire : masqué sur mobile, où il coupait le bouton. --}}
                            <span class="hidden opacity-60 sm:inline">· {{ $collection->products_count }} {{ $collection->products_count > 1 ? 'pièces' : 'pièce' }}</span>
                            <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>
                </div>
            </section>
        @endforeach
    @endif
</x-shop-layout>

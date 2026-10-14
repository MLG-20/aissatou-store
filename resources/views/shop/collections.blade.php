<x-shop-layout :title="'Collections — '.config('app.name')">
    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 pt-12 pb-4">
        <p class="text-xs uppercase tracking-[0.25em] text-brand-signature">Le récit éditorial</p>
        <h1 class="font-display text-4xl sm:text-5xl mt-2">Collections</h1>
        <p class="mt-3 max-w-xl text-brand-muted">Des ensembles choisis par univers — une même collection traverse hijabs, foulards, parfums et objets à offrir.</p>
    </div>

    @if ($collections->isEmpty())
        <p class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-10 text-brand-muted">Nos collections arrivent bientôt.</p>
    @else
        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-10 flex flex-col gap-8 pb-20">
            @foreach ($collections as $collection)
                @php
                    $image = $collection->sizedUrl('large');
                    $alignRight = $loop->even;
                @endphp
                <a
                    href="{{ route('shop.collection', $collection) }}"
                    class="group relative block overflow-hidden rounded-sm min-h-[340px] sm:min-h-[420px] {{ $image ? 'bg-brand-ink' : 'bg-brand-parchment' }}"
                >
                    @if ($image)
                        {{-- Image floutée + voile : le texte reste lisible sur
                             n'importe quelle photo, même claire. --}}
                        <img
                            src="{{ $image }}"
                            alt=""
                            aria-hidden="true"
                            loading="lazy"
                            class="absolute inset-0 h-full w-full object-cover blur-[3px] scale-110 transition-transform duration-700 group-hover:scale-125"
                        >
                        <div class="absolute inset-0 bg-brand-ink/60"></div>
                        <div class="absolute inset-0 {{ $alignRight ? 'bg-gradient-to-l' : 'bg-gradient-to-r' }} from-brand-ink/60 via-transparent to-transparent"></div>
                    @endif

                    {{-- Cadre ouvert clair, écho au Passage Frame (p.9). --}}
                    <div class="absolute inset-4 sm:inset-6 border border-brand-surface/30 pointer-events-none {{ $image ? '' : 'border-brand-ink/20' }}"></div>

                    {{-- Numéro fantôme éditorial. --}}
                    <span aria-hidden="true" class="absolute -bottom-6 {{ $alignRight ? 'left-6' : 'right-6' }} font-display text-[7rem] sm:text-[10rem] leading-none {{ $image ? 'text-brand-surface/10' : 'text-brand-ink/10' }} select-none">
                        {{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}
                    </span>

                    <div class="relative h-full min-h-[inherit] max-w-shell mx-auto px-8 sm:px-14 py-12 flex items-center {{ $alignRight ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-lg {{ $alignRight ? 'text-right' : '' }}">
                            <p class="text-xs uppercase tracking-[0.25em] {{ $image ? 'text-brand-surface/70' : 'text-brand-signature' }}">
                                Collection{{ $collection->products_count > 0 ? ' — '.$collection->products_count.' pièce'.($collection->products_count > 1 ? 's' : '') : '' }}
                            </p>
                            <h2 class="font-display text-4xl sm:text-5xl mt-3 transition-colors duration-300 {{ $image ? 'text-brand-surface group-hover:text-brand-accent' : 'text-brand-ink group-hover:text-brand-signature' }}">
                                {{ $collection->name }}
                            </h2>

                            @if ($collection->description)
                                <p class="mt-4 {{ $image ? 'text-brand-surface/85' : 'text-brand-muted' }}">{{ $collection->description }}</p>
                            @endif

                            <span class="mt-6 inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] {{ $image ? 'text-brand-surface group-hover:text-brand-accent' : 'text-brand-ink group-hover:text-brand-signature' }} transition-colors duration-300">
                                Découvrir
                                <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-shop-layout>

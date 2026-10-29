<x-shop-layout :title="'Journal — '.config('app.name')">
    {{-- JOURNAL — histoires de matières et de gestes, en magazine : le dernier
         article à la une, les suivants en cartes numérotées. Couvertures jamais
         vides (x-shop.post-cover). --}}
    <section class="border-b border-brand-ink/10">
        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-20" data-reveal>
            <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Histoires de matières et de gestes</span>
            <h1 class="mt-4 font-display text-5xl sm:text-7xl leading-[1] text-brand-ink">Journal</h1>
            <p class="mt-6 max-w-xl text-lg leading-relaxed text-brand-muted">Conseils de style, savoir-faire et idées cadeaux : prenez le temps de lire, comme on choisit une étoffe.</p>
        </div>
    </section>

    @if ($posts->isEmpty())
        <p class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 text-brand-muted">Nos premières histoires arrivent bientôt.</p>
    @else
        @php
            $isFirstPage = $posts->onFirstPage();
            $featured = $isFirstPage ? $posts->first() : null;
            $rest = $isFirstPage ? $posts->slice(1) : $posts;
            $numberOf = fn ($post) => str_pad((string) ($posts->total() - ($posts->firstItem() - 1) - $posts->search($post)), 2, '0', STR_PAD_LEFT);
        @endphp

        {{-- À LA UNE --}}
        @if ($featured)
            <section class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-24">
                <a href="{{ route('shop.post', $featured) }}" class="group grid items-center gap-12 lg:grid-cols-[minmax(0,1.15fr),minmax(0,1fr)] lg:gap-20">
                    <div class="relative mx-3 sm:mx-5" data-reveal>
                        <x-shop.post-cover :post="$featured" size="large" :number="$numberOf($featured)" class="aspect-[5/4]" />
                        <div class="passage-frame pointer-events-none absolute -inset-3 sm:-inset-5"></div>
                    </div>

                    <div data-reveal style="--reveal-delay: 150ms">
                        <span class="inline-flex items-center gap-3 text-xs uppercase tracking-[0.3em] text-brand-signature">
                            <span class="h-px w-8 bg-brand-accent"></span>
                            À la une
                        </span>
                        <h2 class="mt-5 font-display text-4xl sm:text-5xl lg:text-6xl leading-[1.05] text-brand-ink transition group-hover:text-brand-signature">{{ $featured->title }}</h2>
                        @if ($featured->excerpt)
                            <p class="mt-6 max-w-lg text-lg leading-relaxed text-brand-muted">{{ $featured->excerpt }}</p>
                        @endif
                        <p class="mt-6 text-xs uppercase tracking-[0.2em] text-brand-muted">
                            {{ $featured->published_at->translatedFormat('j F Y') }} · {{ $featured->readingMinutes() }} min de lecture
                        </p>
                        <span class="mt-8 inline-flex items-center gap-3 border-b border-brand-ink/30 pb-1 text-xs uppercase tracking-[0.2em] text-brand-ink transition group-hover:border-brand-accent">
                            Lire l'article
                            <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                    </div>
                </a>
            </section>
        @endif

        {{-- LES AUTRES ARTICLES --}}
        @if ($rest->isNotEmpty())
            {{-- Dernier bandeau de la page (sans pagination) : `-mb-16` le colle
                 au pied de page, sans bande crème entre les deux. --}}
            <section @class(['border-t border-brand-ink/10 bg-brand-parchment/50', '-mb-16' => ! $posts->hasPages()])>
                <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-20">
                    @if ($featured)
                        <h2 class="mb-10 text-xs uppercase tracking-[0.3em] text-brand-signature" data-reveal>Les précédents numéros</h2>
                    @endif

                    <div class="grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($rest as $post)
                            <a href="{{ route('shop.post', $post) }}" class="group block" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                                <div class="relative">
                                    <x-shop.post-cover :post="$post" :number="$numberOf($post)" class="aspect-[4/3]" />
                                    <div class="passage-frame passage-frame--light pointer-events-none absolute inset-3"></div>
                                </div>
                                <p class="mt-5 flex items-center gap-3 text-[0.75rem] uppercase tracking-[0.2em] text-brand-muted">
                                    <span class="font-medium text-brand-accent">N° {{ $numberOf($post) }}</span>
                                    <span>{{ $post->published_at->translatedFormat('j F Y') }}</span>
                                    <span>· {{ $post->readingMinutes() }} min</span>
                                </p>
                                <h3 class="mt-2 font-display text-2xl leading-snug text-brand-ink transition group-hover:text-brand-signature">{{ $post->title }}</h3>
                                @if ($post->excerpt)
                                    <p class="mt-2 text-sm leading-relaxed text-brand-muted line-clamp-2">{{ $post->excerpt }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($posts->hasPages())
            <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-10">
                {{ $posts->links() }}
            </div>
        @endif
    @endif
</x-shop-layout>

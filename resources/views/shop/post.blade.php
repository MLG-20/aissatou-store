<x-shop-layout :title="$post->title.' — '.config('app.name')" :description="$post->excerpt" :image="$post->coverUrl('share')">
    <article>
        {{-- EN-TÊTE DE MAGAZINE --}}
        <header class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 pt-12 sm:pt-16 text-center" data-reveal>
            <a href="{{ route('shop.journal') }}" class="group inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-brand-muted transition hover:text-brand-signature">
                <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:-translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Journal
            </a>

            <p class="mt-8 text-xs uppercase tracking-[0.25em] text-brand-signature">
                {{ $post->published_at->translatedFormat('j F Y') }} · {{ $post->readingMinutes() }} min de lecture
            </p>
            <h1 class="mx-auto mt-5 max-w-4xl font-display text-4xl sm:text-6xl lg:text-7xl leading-[1.05] text-brand-ink">{{ $post->title }}</h1>
        </header>

        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 mt-12 sm:mt-16" data-reveal style="--reveal-delay: 120ms">
            <div class="relative mx-3 sm:mx-5">
                <x-shop.post-cover :post="$post" size="large" class="aspect-[16/9] lg:aspect-[2/1]" />
                <div class="passage-frame pointer-events-none absolute -inset-3 sm:-inset-5"></div>
            </div>
        </div>

        {{-- CORPS : chapeau en grand, lettrine Bodoni Moda, ligne de lecture
             confortable. Contenu assaini côté modèle (Post::safeContent) : pas
             de script, même si la source était compromise. --}}
        <div class="mx-auto max-w-2xl px-4 sm:px-6 py-14 sm:py-20">
            @if ($post->excerpt)
                <p class="font-display text-2xl sm:text-3xl leading-snug text-brand-ink">{{ $post->excerpt }}</p>
                <span class="mt-8 block h-px w-16 bg-brand-accent"></span>
            @endif

            <div class="mt-10 space-y-6 text-lg leading-[1.85] text-brand-ink/85
                        [&>p:first-child]:first-letter:float-left [&>p:first-child]:first-letter:mr-3 [&>p:first-child]:first-letter:mt-1 [&>p:first-child]:first-letter:font-display [&>p:first-child]:first-letter:text-7xl [&>p:first-child]:first-letter:leading-[0.8] [&>p:first-child]:first-letter:text-brand-signature
                        [&_h2]:mt-12 [&_h2]:font-display [&_h2]:text-3xl [&_h2]:text-brand-ink [&_h3]:mt-8 [&_h3]:font-display [&_h3]:text-2xl
                        [&_a]:text-brand-signature [&_a]:underline [&_a]:underline-offset-4
                        [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6
                        [&_blockquote]:my-10 [&_blockquote]:border-l-2 [&_blockquote]:border-brand-accent [&_blockquote]:pl-6 [&_blockquote]:font-display [&_blockquote]:text-2xl [&_blockquote]:italic [&_blockquote]:text-brand-ink">
                {!! $post->safeContent() !!}
            </div>

            <div class="mt-14 flex flex-wrap items-center justify-between gap-4 border-t border-brand-ink/10 pt-8">
                <p class="text-sm text-brand-muted">Cet article vous a plu ? Partagez-le.</p>
                <x-shop.share :url="route('shop.post', $post)" :text="$post->title" />
            </div>
        </div>
    </article>

    {{-- LES PIÈCES DE CET ARTICLE --}}
    @if ($post->products->isNotEmpty())
        <section class="border-t border-brand-ink/10 bg-brand-parchment/60">
            <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-20">
                <div class="mb-10" data-reveal>
                    <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">À retrouver en boutique</span>
                    <h2 class="mt-3 font-display text-3xl sm:text-4xl text-brand-ink">Les pièces de cet article</h2>
                </div>
                <div class="grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4" data-reveal>
                    @foreach ($post->products as $product)
                        <x-shop.product-card :product="$product" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- À LIRE AUSSI --}}
    @if ($others->isNotEmpty())
        <section class="border-t border-brand-ink/10">
            <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-20">
                <div class="mb-10 flex items-end justify-between gap-4" data-reveal>
                    <h2 class="font-display text-3xl text-brand-ink">À lire aussi</h2>
                    <a href="{{ route('shop.journal') }}" class="whitespace-nowrap text-xs uppercase tracking-[0.2em] text-brand-ink/70 transition hover:text-brand-signature">Tout le journal →</a>
                </div>
                <div class="grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($others as $other)
                        <a href="{{ route('shop.post', $other) }}" class="group block" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
                            <x-shop.post-cover :post="$other" class="aspect-[4/3]" />
                            <p class="mt-4 text-[0.75rem] uppercase tracking-[0.2em] text-brand-muted">{{ $other->published_at->translatedFormat('j F Y') }} · {{ $other->readingMinutes() }} min</p>
                            <h3 class="mt-2 font-display text-xl leading-snug text-brand-ink transition group-hover:text-brand-signature">{{ $other->title }}</h3>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-shop-layout>

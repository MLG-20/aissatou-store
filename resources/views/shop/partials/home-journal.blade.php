{{-- LE JOURNAL — les derniers articles en mise en page magazine : c'est le
     contenu qui fait rester (conseils de drapé, idées cadeaux), et chaque
     article renvoie vers ses pièces liées. Un article seul s'étale sur toute
     la largeur, deux se partagent la ligne en 2/3 – 1/3.

     @var \Illuminate\Support\Collection<\App\Models\Content\Post> $posts --}}
@if ($posts->isNotEmpty())
    <section class="border-t border-brand-ink/10">
        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-20 sm:py-28">
            <div class="mb-10 flex items-end justify-between gap-4 sm:mb-14" data-reveal>
                <div>
                    <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Le Journal</span>
                    <h2 class="mt-3 font-display text-4xl leading-[1.05] text-brand-ink sm:text-5xl">Inspirations &amp; conseils</h2>
                </div>

                <a href="{{ route('shop.journal') }}" class="group inline-flex items-center gap-2 whitespace-nowrap text-xs uppercase tracking-[0.2em] text-brand-ink">
                    <span class="border-b border-brand-ink/30 pb-1 transition group-hover:border-brand-accent">Tout lire</span>
                    <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            </div>

            <div class="grid grid-cols-1 gap-10 sm:gap-8 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @php
                        $span = match (true) {
                            $posts->count() === 1 => 'lg:col-span-3',
                            $posts->count() === 2 && $loop->first => 'lg:col-span-2',
                            default => '',
                        };
                    @endphp

                    <a href="{{ route('shop.post', $post) }}" class="group block {{ $span }}" data-reveal style="--reveal-delay: {{ $loop->index * 120 }}ms">
                        <x-shop.post-cover :post="$post" size="large" :number="str_pad($loop->iteration, 2, '0', STR_PAD_LEFT)" class="aspect-[4/3] rounded-sm lg:aspect-auto lg:h-[26rem]" />

                        <div class="mt-5 flex items-center gap-3 text-[0.75rem] uppercase tracking-[0.2em] text-brand-muted">
                            <span>{{ $post->published_at->translatedFormat('j F Y') }}</span>
                            <span class="h-px w-6 bg-brand-ink/20" aria-hidden="true"></span>
                            <span>{{ $post->readingMinutes() }} min de lecture</span>
                        </div>

                        <h3 class="mt-3 font-display text-2xl leading-snug text-brand-ink transition group-hover:text-brand-signature sm:text-3xl">{{ $post->title }}</h3>

                        @if ($post->excerpt)
                            <p class="mt-3 max-w-xl text-sm leading-relaxed text-brand-muted line-clamp-2">{{ $post->excerpt }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

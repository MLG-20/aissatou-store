<x-shop-layout :title="'Journal — '.config('app.name')">
    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8">
        <p class="text-xs uppercase tracking-[0.25em] text-brand-signature">Histoires de matières et de gestes</p>
        <h1 class="font-display text-4xl sm:text-5xl mt-2">Journal</h1>

        @if ($posts->isEmpty())
            <p class="mt-6 text-brand-muted">Nos premières histoires arrivent bientôt.</p>
        @else
            <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <a href="{{ route('shop.post', $post) }}" class="group block">
                        <div class="aspect-[16/10] w-full overflow-hidden rounded-sm bg-brand-parchment">
                            @if ($image = $post->sizedUrl('medium'))
                                <img
                                    src="{{ $image }}"
                                    alt="{{ $post->title }}"
                                    loading="lazy"
                                    class="h-full w-full object-cover object-center transition duration-700 group-hover:scale-[1.03]"
                                >
                            @endif
                        </div>
                        <p class="mt-3 text-xs uppercase tracking-[0.18em] text-brand-muted">
                            {{ $post->published_at->format('d/m/Y') }}
                        </p>
                        <h2 class="font-display text-xl text-brand-ink mt-1 group-hover:text-brand-signature transition">{{ $post->title }}</h2>
                        @if ($post->excerpt)
                            <p class="mt-1 text-sm text-brand-muted line-clamp-2">{{ $post->excerpt }}</p>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</x-shop-layout>

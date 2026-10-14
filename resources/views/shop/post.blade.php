<x-shop-layout :title="$post->title.' — '.config('app.name')">
    <article class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
        <a href="{{ route('shop.journal') }}" class="text-sm underline text-brand-muted hover:text-brand-signature">
            ← Retour au journal
        </a>

        <p class="mt-6 text-xs uppercase tracking-[0.25em] text-brand-signature">
            Journal — {{ $post->published_at->format('d/m/Y') }}
        </p>
        <h1 class="font-display text-4xl sm:text-5xl mt-2">{{ $post->title }}</h1>

        @if ($image = $post->sizedUrl('large'))
            <img
                src="{{ $image }}"
                alt="{{ $post->title }}"
                class="mt-8 w-full rounded-sm object-cover object-center max-h-[420px]"
            >
        @endif

        {{-- Contenu assaini côté modèle (Post::safeContent) : pas de script,
             même si la source était compromise. --}}
        <div class="mt-8 space-y-4 text-brand-ink leading-relaxed [&_h2]:font-display [&_h2]:text-2xl [&_h2]:mt-8 [&_a]:underline [&_a]:text-brand-signature [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:list-decimal [&_ol]:pl-6 [&_blockquote]:border-l-2 [&_blockquote]:border-brand-signature [&_blockquote]:pl-4 [&_blockquote]:italic">
            {!! $post->safeContent() !!}
        </div>

        @if ($post->products->isNotEmpty())
            <section class="mt-12 border-t border-brand-ink/10 pt-8">
                <h2 class="font-display text-2xl">Dans cet article</h2>
                <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:gap-x-6 lg:grid-cols-4">
                    @foreach ($post->products as $product)
                        <x-shop.product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-shop-layout>

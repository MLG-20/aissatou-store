<x-shop-layout :title="$page->title.' — '.config('app.name')">
    {{-- Page légale : texte écrit en admin (Contenu du site > Pages légales),
         assaini et complété par l'identité légale des réglages
         (LegalPage::renderedContent). Mise en page sobre : on lit, on ne vend pas. --}}
    <article class="mx-auto max-w-3xl px-4 sm:px-6 py-14 sm:py-20">
        <p class="text-xs uppercase tracking-[0.3em] text-brand-sage">Informations légales</p>
        <h1 class="mt-4 font-display text-4xl sm:text-5xl text-brand-ink">{{ $page->title }}</h1>
        <p class="mt-4 text-sm text-brand-muted">
            Dernière mise à jour le {{ $page->updated_at->translatedFormat('j F Y') }}
        </p>
        <span class="mt-8 block h-px w-16 bg-brand-accent"></span>

        <div class="mt-10 space-y-5 leading-[1.8] text-brand-ink/85
                    [&_h2]:mt-12 [&_h2]:font-display [&_h2]:text-2xl sm:[&_h2]:text-3xl [&_h2]:text-brand-ink
                    [&_h3]:mt-8 [&_h3]:font-display [&_h3]:text-xl [&_h3]:text-brand-ink
                    [&_a]:text-brand-signature [&_a]:underline [&_a]:underline-offset-4
                    [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:space-y-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:space-y-2
                    [&_blockquote]:border-l-2 [&_blockquote]:border-brand-accent [&_blockquote]:pl-6 [&_blockquote]:italic">
            {!! $page->renderedContent() !!}
        </div>
    </article>
</x-shop-layout>

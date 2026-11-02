<x-shop-layout :title="'À propos — '.config('app.name')" :description="\App\Models\Content\SiteSetting::current()->seo_about_description">
    {{-- Photo pleine largeur en attendant une vraie photographie
    (fondatrice/atelier) fournie par la cliente — voir doc UX/UI 6.7. --}}
    <section class="relative w-full h-[45vh] min-h-[320px] max-h-[480px] bg-brand-ink flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_20%_30%,rgba(74,24,51,0.6),transparent_60%)]"></div>
        <h1 class="relative font-display text-4xl sm:text-5xl text-brand-surface">Notre histoire</h1>
    </section>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 space-y-10">
        @if ($settings->about_story)
            <div>
                <span class="text-xs uppercase tracking-[0.2em] text-brand-sage">Depuis Dakar</span>
                <h2 class="font-display text-2xl sm:text-3xl text-brand-ink mt-2 mb-4">Aissatou Store</h2>

                @foreach (explode("\n\n", $settings->about_story) as $paragraph)
                    <p class="text-brand-ink/80 leading-relaxed {{ $loop->first ? '' : 'mt-4' }}">{{ $paragraph }}</p>
                @endforeach
            </div>
        @endif

        @if ($settings->about_quote)
            <div class="border-y border-brand-ink/10 py-8">
                <blockquote class="font-display text-xl sm:text-2xl text-brand-ink text-center italic">
                    « {{ $settings->about_quote }} »
                </blockquote>
            </div>
        @endif

        @if (! empty($settings->about_values))
            <div>
                <h2 class="font-display text-2xl text-brand-ink mb-4">Nos valeurs</h2>
                <div class="grid sm:grid-cols-3 gap-6">
                    @foreach ($settings->about_values as $value)
                        <div>
                            <p class="font-medium text-brand-ink">{{ $value['title'] }}</p>
                            <p class="text-sm text-brand-muted mt-1">{{ $value['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-shop-layout>

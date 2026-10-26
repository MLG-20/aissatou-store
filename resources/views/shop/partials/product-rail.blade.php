{{-- Rangée de suggestions sous la fiche produit (« Complétez votre tenue »,
     « Vous aimerez aussi », « Vus récemment »). Grille sur grand écran ;
     sur téléphone, une piste qu'on fait glisser au doigt, carte par carte,
     la suivante dépassant du bord pour inviter au geste.

     @var string $eyebrow
     @var string $title
     @var \Illuminate\Support\Collection $products
     @var bool $muted  fond Parchemin (alternance des sections) --}}
@if ($products->isNotEmpty())
    <section @class(['border-t border-brand-ink/10', 'bg-brand-parchment/50' => $muted ?? false])>
        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-20">
            <div class="mb-8 sm:mb-10" data-reveal>
                <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">{{ $eyebrow }}</span>
                <h2 class="mt-3 font-display text-3xl sm:text-4xl text-brand-ink leading-[1.05]">{{ $title }}</h2>
            </div>

            <div class="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-5 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:gap-6 sm:overflow-visible sm:px-0 lg:grid-cols-4" data-reveal>
                @foreach ($products as $item)
                    <div class="w-[62%] flex-shrink-0 snap-start sm:w-auto" style="--reveal-delay: {{ $loop->index * 80 }}ms">
                        <x-shop.product-card :product="$item" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

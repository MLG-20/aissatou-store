{{-- L'ART D'OFFRIR — le cadeau est un moment d'achat à part (Korité,
     Tabaski, anniversaires, mariages) : on y entre par le BUDGET, la première
     question qu'on se pose, plutôt que par la catégorie. Chaque tranche mène
     au catalogue filtré par prix ; une tranche vide n'est pas proposée.

     Fond Cassis : la seule grande surface de la couleur signature sur la
     page, pour que la section se vive comme un écrin.

     @var ?\App\Models\Catalogue\Category $giftCategory
     @var list<array{label: string, hint: string, count: int, query: array}> $giftBudgets --}}
@if (! empty($giftBudgets))
    <section class="relative overflow-hidden bg-brand-signature text-brand-surface">
        <span
            aria-hidden="true"
            class="brand-symbol pointer-events-none absolute -left-32 -bottom-24 w-[30rem] text-brand-surface/[0.04]"
            style="--brand-symbol: url('{{ asset('brand/symbole-128.png') }}')"
        ></span>

        <div class="relative max-w-shell mx-auto grid grid-cols-1 items-center gap-12 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-2 lg:gap-20 lg:px-10 xl:px-12">
            @if ($giftImage = $giftCategory?->sizedUrl('large'))
                <a href="{{ route('shop.category', $giftCategory) }}" class="group relative block" data-reveal>
                    <div class="relative aspect-[4/5] overflow-hidden rounded-sm sm:aspect-square">
                        <img src="{{ $giftImage }}" alt="Coffrets et cadeaux Aissatou Store" loading="lazy" class="h-full w-full object-cover transition duration-[1400ms] ease-out group-hover:scale-[1.04]">
                        <div class="absolute inset-0 bg-gradient-to-t from-brand-ink/50 via-transparent to-transparent"></div>
                    </div>
                    <div class="passage-frame passage-frame--light absolute inset-4 sm:inset-6"></div>
                    <span class="absolute bottom-8 left-8 text-[0.65rem] uppercase tracking-[0.3em] text-brand-surface/85 sm:bottom-10 sm:left-10">Emballage soigné, mot personnalisé</span>
                </a>
            @endif

            <div data-reveal style="--reveal-delay: 150ms">
                <span class="text-xs uppercase tracking-[0.3em] text-brand-parchment/70">L'art d'offrir</span>
                <h2 class="mt-4 font-display text-4xl leading-[1.05] sm:text-5xl lg:text-6xl">Un présent qui leur ressemble</h2>
                <p class="mt-6 max-w-md text-base leading-relaxed text-brand-surface/70 sm:text-lg">
                    Pour une Korité, un anniversaire ou simplement pour dire merci : choisissez votre budget, nous avons déjà choisi les pièces.
                </p>

                <ul class="mt-10 border-t border-brand-surface/20">
                    @foreach ($giftBudgets as $budget)
                        <li class="border-b border-brand-surface/20">
                            <a href="{{ route('shop.index', $budget['query']) }}" class="group flex items-center gap-6 py-5 sm:py-6">
                                <span class="flex-1 sm:flex sm:items-center sm:gap-6">
                                    <span class="block text-[0.6rem] uppercase tracking-[0.25em] text-brand-sage sm:w-32 sm:flex-shrink-0">{{ $budget['hint'] }}</span>
                                    <span class="mt-1 block font-display text-xl transition-transform duration-500 group-hover:translate-x-2 sm:mt-0 sm:text-2xl">{{ $budget['label'] }}</span>
                                </span>
                                <span class="hidden text-xs text-brand-surface/50 sm:inline">{{ $budget['count'] }} {{ \Illuminate\Support\Str::plural('pièce', $budget['count']) }}</span>
                                <svg class="h-4 w-4 flex-shrink-0 text-brand-surface/60 transition duration-300 group-hover:translate-x-1 group-hover:text-brand-surface" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($giftCategory)
                    <div class="mt-10">
                        <x-shop.cta :href="route('shop.category', $giftCategory)" on-dark>
                            Voir les coffrets &amp; cadeaux
                        </x-shop.cta>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif

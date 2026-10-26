{{-- LE LOOK — une photo de mise en situation dont chaque point ouvre la
     pièce portée. C'est la vitrine « magazine » de l'accueil : on ne regarde
     plus un produit isolé mais une tenue, et chaque pièce est à un clic.

     Les points sont placés en % de la photo (Filament > Le Look), sur une
     image affichée à son ratio naturel — jamais recadrée, sinon les points
     tomberaient à côté des pièces. Survoler une ligne de la liste allume le
     point correspondant, et inversement. Plusieurs looks : flèches + compteur.

     @var \Illuminate\Support\Collection<\App\Models\Content\Look> $looks --}}
@if ($looks->isNotEmpty())
    @php $fcfa = fn ($amount) => number_format($amount, 0, ',', ' ').' FCFA'; @endphp

    <section
        class="relative overflow-hidden bg-brand-ink text-brand-surface"
        x-data="{ look: 0, spot: null, count: {{ $looks->count() }}, go(step) { this.spot = null; this.look = (this.look + step + this.count) % this.count; } }"
        @keydown.escape="spot = null"
    >
        <x-shop.fold-backdrop :cut="false" class="opacity-60" />

        <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-20 sm:py-28">
            @foreach ($looks as $lookIndex => $look)
                <div
                    x-show="look === {{ $lookIndex }}"
                    @if ($lookIndex > 0) x-cloak @endif
                    x-transition:enter="transition duration-700 ease-out"
                    x-transition:enter-start="opacity-0"
                    class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12 lg:gap-16"
                >
                    {{-- La photo et ses points. --}}
                    <div class="lg:col-span-6 lg:col-start-1" data-reveal>
                        <div class="relative mx-auto max-w-md lg:max-w-lg">
                            <div class="passage-frame passage-frame--light pointer-events-none absolute -inset-3 sm:-inset-5"></div>

                            <div class="relative">
                                <img
                                    src="{{ $look->sizedUrl('large') }}"
                                    srcset="{{ $look->sizedUrl('medium') }} 800w, {{ $look->sizedUrl('large') }} 1400w"
                                    sizes="(min-width: 1024px) 40vw, 100vw"
                                    alt="{{ $look->title }}"
                                    loading="lazy"
                                    class="block h-auto w-full rounded-sm"
                                >

                                @foreach ($look->hotspots as $spotIndex => $hotspot)
                                    <button
                                        type="button"
                                        class="absolute -translate-x-1/2 -translate-y-1/2 focus:outline-none"
                                        style="left: {{ $hotspot->x }}%; top: {{ $hotspot->y }}%"
                                        @click="spot = spot === {{ $spotIndex }} ? null : {{ $spotIndex }}"
                                        @mouseenter="spot = {{ $spotIndex }}"
                                        :aria-expanded="spot === {{ $spotIndex }}"
                                        aria-label="Voir la pièce : {{ $hotspot->product->name }}"
                                    >
                                        <span class="absolute inset-0 rounded-full bg-brand-surface/50 motion-safe:animate-ping" :class="spot === {{ $spotIndex }} && 'hidden'" aria-hidden="true"></span>
                                        <span
                                            class="relative flex h-9 w-9 items-center justify-center rounded-full bg-brand-surface text-xs font-medium text-brand-signature shadow-[0_4px_18px_rgba(0,0,0,0.35)] ring-4 ring-brand-surface/25 transition duration-300"
                                            :class="spot === {{ $spotIndex }} && '!bg-brand-accent !text-brand-surface scale-110 ring-brand-accent/30'"
                                        >{{ $spotIndex + 1 }}</span>
                                    </button>
                                @endforeach

                                {{-- Fiche express de la pièce, posée en bas de la
                                     photo : toujours lisible, sans déborder de
                                     l'écran quel que soit l'endroit du point. --}}
                                @foreach ($look->hotspots as $spotIndex => $hotspot)
                                    @php $piece = $hotspot->product; @endphp
                                    <div
                                        x-show="spot === {{ $spotIndex }}"
                                        x-cloak
                                        x-transition:enter="transition duration-300 ease-out"
                                        x-transition:enter-start="opacity-0 translate-y-3"
                                        x-transition:leave="transition duration-150 ease-in"
                                        x-transition:leave-end="opacity-0"
                                        class="absolute inset-x-3 bottom-3 sm:inset-x-5 sm:bottom-5"
                                    >
                                        <a href="{{ route('shop.product', $piece) }}" class="group flex items-center gap-4 rounded-sm bg-brand-surface/95 p-3 pr-5 text-brand-ink shadow-2xl backdrop-blur">
                                            @if ($thumb = $piece->primaryImage())
                                                <img src="{{ $thumb->sizedUrl('thumb') }}" alt="" class="h-16 w-14 flex-shrink-0 rounded-sm object-cover">
                                            @endif
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-[0.6rem] uppercase tracking-[0.2em] text-brand-muted">{{ $piece->category->name }}</span>
                                                <span class="block truncate font-display text-lg leading-tight">{{ $piece->name }}</span>
                                                <span class="block text-sm text-brand-muted">{{ $fcfa($piece->price) }}</span>
                                            </span>
                                            <svg class="h-4 w-4 flex-shrink-0 text-brand-signature transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                            </svg>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Le récit et la liste des pièces. --}}
                    <div class="lg:col-span-5 lg:col-start-8" data-reveal style="--reveal-delay: 150ms">
                        <div class="flex items-center justify-between gap-6">
                            <span class="text-xs uppercase tracking-[0.3em] text-brand-sage">Le Look</span>

                            @if ($looks->count() > 1)
                                <div class="flex items-center gap-4 text-xs tracking-[0.2em] text-brand-surface/60">
                                    <button type="button" @click="go(-1)" class="p-1 transition hover:text-brand-surface" aria-label="Look précédent">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                                    </button>
                                    <span>{{ str_pad($lookIndex + 1, 2, '0', STR_PAD_LEFT) }} / {{ str_pad($looks->count(), 2, '0', STR_PAD_LEFT) }}</span>
                                    <button type="button" @click="go(1)" class="p-1 transition hover:text-brand-surface" aria-label="Look suivant">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                    </button>
                                </div>
                            @endif
                        </div>

                        <h2 class="mt-5 font-display text-4xl leading-[1.05] sm:text-5xl lg:text-6xl">{{ $look->title }}</h2>

                        @if ($look->subtitle)
                            <p class="mt-6 max-w-md text-base leading-relaxed text-brand-surface/65 sm:text-lg">{{ $look->subtitle }}</p>
                        @endif

                        <ol class="mt-10 border-t border-brand-surface/15">
                            @foreach ($look->hotspots as $spotIndex => $hotspot)
                                @php $piece = $hotspot->product; @endphp
                                <li class="border-b border-brand-surface/15">
                                    <a
                                        href="{{ route('shop.product', $piece) }}"
                                        class="group flex items-center gap-5 py-4 transition"
                                        @mouseenter="spot = {{ $spotIndex }}"
                                        @focus="spot = {{ $spotIndex }}"
                                    >
                                        <span
                                            class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border border-brand-surface/30 text-[0.65rem] transition duration-300"
                                            :class="spot === {{ $spotIndex }} && '!border-brand-accent bg-brand-accent'"
                                        >{{ $spotIndex + 1 }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[0.6rem] uppercase tracking-[0.2em] text-brand-surface/45">{{ $piece->category->name }}</span>
                                            <span class="block font-display text-xl leading-snug transition duration-300 sm:truncate" :class="spot === {{ $spotIndex }} ? 'translate-x-1' : ''">{{ $piece->name }}</span>
                                        </span>
                                        <span class="whitespace-nowrap text-sm text-brand-surface/70">{{ $fcfa($piece->price) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>

                        @if ($look->hotspots->count() > 1)
                            <p class="mt-6 flex items-baseline justify-between text-xs uppercase tracking-[0.2em] text-brand-surface/50">
                                <span>La tenue complète</span>
                                <span class="font-display text-2xl normal-case tracking-normal text-brand-surface">{{ $fcfa($look->hotspots->sum(fn ($hotspot) => $hotspot->product->price)) }}</span>
                            </p>
                        @endif

                        <p class="mt-8 text-xs text-brand-surface/40">Touchez un point sur la photo pour découvrir la pièce.</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

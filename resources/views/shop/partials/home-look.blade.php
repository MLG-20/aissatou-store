{{-- LE LOOK — une photo ou une vidéo (30 s au plus, muette) de mise en
     situation et la liste des pièces portées. Les looks défilent seuls : une
     vidéo passe au suivant à sa fin, une photo quand ses pièces se sont toutes
     allumées (6 s au moins).
     C'est la vitrine « magazine » de l'accueil : on ne regarde plus un
     produit isolé mais une tenue, et chaque pièce est à un clic.

     La photo reste nette, sans point posé dessus : survoler une ligne de la
     liste affiche la fiche de la pièce en bas de la photo. Plusieurs looks :
     flèches + compteur. (Les positions x/y des points, réglées dans Filament
     > Le Look, ne sont plus affichées.)

     Sans interaction, les pièces s'allument tour à tour (fiche comprise) tant
     que la section est à l'écran ; la main reprend dès que le pointeur entre
     dans la section, et le défilement repart quand il en sort.

     @var \Illuminate\Support\Collection<\App\Models\Content\Look> $looks --}}
@if ($looks->isNotEmpty())
    @php $fcfa = fn ($amount) => number_format($amount, 0, ',', ' ').' FCFA'; @endphp

    <section
        class="relative overflow-hidden bg-brand-ink text-brand-surface"
        x-data="{
            look: 0, spot: null, count: {{ $looks->count() }},
            spots: @js($looks->map(fn ($look) => $look->hotspots->count())->values()),
            kinds: @js($looks->map(fn ($look) => $look->isVideo() ? 'video' : 'image')->values()),
            paused: false, visible: false, onLook: 0,
            // Animations réduites ou économie de données : ni lecture
            // automatique ni défilement, l'image d'aperçu reste affichée.
            still: matchMedia('(prefers-reduced-motion: reduce)').matches || navigator.connection?.saveData === true,
            go(step) {
                this.spot = null;
                this.onLook = 0;
                this.look = (this.look + step + this.count) % this.count;
                this.$nextTick(() => this.play(true));
            },
            next() {
                if (this.paused || ! this.visible || ! this.spots[this.look]) return;
                this.spot = this.spot === null ? 0 : (this.spot + 1) % this.spots[this.look];
            },
            // Seule la vidéo du look affiché joue, et seulement à l'écran ;
            // elle ne se télécharge qu'à ce moment-là (preload none).
            play(restart) {
                this.$root.querySelectorAll('video').forEach((video) => +video.dataset.look === this.look || video.pause());
                const video = this.$refs['video' + this.look];
                if (! video || this.still || ! this.visible) return;
                if (restart) video.currentTime = 0;
                video.play().catch(() => {});
            },
            // Fin de la vidéo : look suivant, ou on la rejoue (look unique,
            // ou visiteuse en train de regarder la section).
            ended() { this.count > 1 && ! this.paused ? this.go(1) : this.play(true); },
            // Look en photo : affiché le temps que ses pièces s'allument
            // toutes, 6 s au moins.
            tick() {
                if (this.still || this.paused || ! this.visible || this.count < 2 || this.kinds[this.look] !== 'image') return;
                if (++this.onLook >= Math.max(6, Math.ceil(this.spots[this.look] * 2.8))) this.go(1);
            },
        }"
        x-init="
            new IntersectionObserver(([entry]) => {
                visible = entry.isIntersecting;
                visible ? play(false) : $root.querySelectorAll('video').forEach((video) => video.pause());
            }, { threshold: 0.4 }).observe($el);
            setInterval(() => next(), 2800);
            setInterval(() => tick(), 1000);
        "
        @mouseenter="paused = true"
        @mouseleave="paused = false"
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
                    <div class="group lg:col-span-6 lg:col-start-1" data-reveal>
                        <div class="relative mx-auto max-w-md lg:max-w-lg">
                            <div class="passage-frame passage-frame--light pointer-events-none absolute -inset-3 sm:-inset-5"></div>

                            <div class="relative">
                                {{-- Lent zoom de la photo au survol. --}}
                                <div class="overflow-hidden rounded-sm">
                                    @if ($look->isVideo())
                                        {{-- Muette (condition de la lecture automatique
                                             sur téléphone), cadrée au format du look ;
                                             sans lecture automatique, les commandes
                                             permettent de la lancer soi-même. --}}
                                        <video
                                            x-ref="video{{ $lookIndex }}"
                                            data-look="{{ $lookIndex }}"
                                            src="{{ $look->videoUrl() }}"
                                            poster="{{ $look->sizedUrl('large') }}"
                                            muted
                                            playsinline
                                            preload="none"
                                            :controls="still"
                                            @ended="ended()"
                                            aria-label="{{ $look->title }}"
                                            class="block aspect-[2/3] w-full object-cover transition duration-[1400ms] ease-out motion-safe:group-hover:scale-[1.04]"
                                        ></video>
                                    @else
                                        <img
                                            src="{{ $look->sizedUrl('large') }}"
                                            srcset="{{ $look->sizedUrl('medium') }} 800w, {{ $look->sizedUrl('large') }} 1400w"
                                            sizes="(min-width: 1024px) 40vw, 100vw"
                                            alt="{{ $look->title }}"
                                            loading="lazy"
                                            class="block h-auto w-full transition duration-[1400ms] ease-out motion-safe:group-hover:scale-[1.04]"
                                        >
                                    @endif
                                </div>

                                {{-- Fiche express de la pièce, posée en bas de la
                                     photo : toujours lisible, sans déborder de
                                     l'écran. --}}
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
                                {{-- Les lignes apparaissent l'une après l'autre. --}}
                                <li class="border-b border-brand-surface/15" data-reveal style="--reveal-delay: {{ 250 + $spotIndex * 120 }}ms">
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

                        {{-- L'accueil n'en montre qu'un aperçu : le Lookbook les a tous. --}}
                        <a href="{{ route('shop.lookbook.look', $look) }}" class="group mt-10 inline-flex items-center gap-3 text-xs uppercase tracking-[0.25em] text-brand-surface/70 transition hover:text-brand-surface">
                            Voir le Lookbook
                            <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

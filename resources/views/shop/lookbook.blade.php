{{-- LE LOOKBOOK — la vitrine émotionnelle de la maison : chaque look occupe
     tout l'écran et l'on passe au suivant d'un geste (défilement aimanté).
     Ouverture (signature + manifeste), les looks, puis une fin qui ramène vers
     la collection ou une conseillère.

     Retenue plutôt qu'effets : fondus lents, texte qui arrive après l'image,
     léger zoom. Sur ordinateur, la vidéo verticale occupe le centre sur toute
     la hauteur, posée sur un fond tiré de la même image (flouté, assombri),
     les textes dans les marges comme dans un magazine.

     Données mobiles : seule la vidéo à l'écran joue ; la voisine se prépare
     juste avant d'arriver. Animations réduites ou économie de données :
     images fixes, lecture à la demande.

     Défilement automatique, comme des stories : une vidéo (envoyée ou
     YouTube) passe au look suivant à sa fin, une photo au bout de 7 s,
     l'ouverture au bout de 5 s ; une barre fine montre le temps restant.
     Pause : bouton entre les flèches (la vidéo tourne alors en boucle),
     panneau des pièces ouvert, onglet caché. Le geste reste libre.

     Acheter sans quitter l'ambiance : « Les pièces du look » ouvre un panneau
     (ajout au panier, ou commande du look entier sur WhatsApp). L'adresse
     suit le look affiché (/lookbook/lumiere-doree) : c'est elle qu'on partage. --}}
@php
    $fcfa = fn ($amount) => number_format($amount, 0, ',', ' ').' FCFA';
    $total = $looks->count();
    $pad = fn (int $number) => str_pad($number, 2, '0', STR_PAD_LEFT);
    $startIndex = $current ? $looks->search(fn ($look) => $look->is($current)) : null;
    $cover = $current ?? $looks->first();
    $whatsapp = filled($settings->contact_whatsapp);
@endphp

<x-shop-layout
    :title="($current ? $current->title.' — ' : '').'Lookbook — '.config('app.name')"
    :transparent-header="true"
    :description="$current ? ($current->mood ?: $current->subtitle) : 'Les looks Aissatou Store : hijabs, foulards et pièces choisies, portés et mis en scène.'"
    :image="$cover?->sizedUrl('large')"
>
    <div
        class="relative bg-brand-ink text-brand-surface"
        x-data="{
            active: -1, sheet: null, copied: null,
            still: matchMedia('(prefers-reduced-motion: reduce)').matches || navigator.connection?.saveData === true,
            auto: true, elapsed: 0, progress: 0, total: {{ $total }},
            kinds: @js($looks->map(fn ($look) => $look->isVideo() ? 'video' : ($look->isYouTube() ? 'youtube' : 'image'))->values()),
            go(step) { $refs.scroller.scrollBy({ top: step * $refs.scroller.clientHeight, behavior: 'smooth' }); },
            // Le défilement automatique tourne-t-il en ce moment ?
            running() { return this.auto && ! this.still && this.sheet === null && ! document.hidden && this.active < this.total; },
            // Toutes les 100 ms : avance du temps des photos et de l'ouverture
            // (les vidéos donnent leur propre progression).
            tick() {
                if (! this.running()) return;
                const kind = this.active < 0 ? 'intro' : this.kinds[this.active];
                if (kind === 'video') return;
                this.elapsed += 0.1;
                const duration = kind === 'intro' ? 5 : (kind === 'image' ? 7 : 120);
                if (kind !== 'youtube') this.progress = Math.min(1, this.elapsed / duration);
                if (this.elapsed >= duration) this.next();
            },
            next() { this.elapsed = 0; this.progress = 0; this.go(1); },
            // Vidéo envoyée : progression et fin.
            timeupdate(video, index) { if (index === this.active && video.duration) this.progress = video.currentTime / video.duration; },
            ended(video, index) {
                if (index !== this.active) return;
                this.running() ? this.next() : video.play().catch(() => {});
            },
            // Lecteur YouTube : il annonce son état par messages.
            youtube(event) {
                let host;
                try { host = new URL(event.origin).hostname; } catch { return; }
                if (! /(^|\.)youtube(-nocookie)?\.com$/.test(host)) return;
                let data;
                try { data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data; } catch { return; }
                const info = data?.info ?? {};
                const frame = [...this.$root.querySelectorAll('iframe')].find((iframe) => iframe.contentWindow === event.source);
                if (! frame) return;
                frame.dataset.ready = '1';
                if (+frame.dataset.look !== this.active) return;
                if (info.duration) frame.dataset.duration = info.duration;
                if (info.currentTime && ! info.duration && frame.dataset.duration) info.duration = +frame.dataset.duration;
                if (info.currentTime && info.duration) this.progress = info.currentTime / info.duration;
                let state = data.event === 'onStateChange' ? data.info : info.playerState;
                // Les Shorts rebouclent d'eux-mêmes sans jamais annoncer leur fin :
                // un retour au début après la dernière seconde vaut une fin.
                if (typeof info.currentTime === 'number') {
                    const last = +(frame.dataset.time ?? 0);
                    if (info.currentTime < 1 && last > Math.max(2, (+frame.dataset.duration || 0) - 2)) state = 0;
                    frame.dataset.time = info.currentTime;
                }
                if (state === 0) {
                    frame.dataset.time = 0;
                    this.running()
                        ? this.next()
                        : frame.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'seekTo', args: [0, true] }), '*');
                }
            },
            // Le lecteur n'écoute qu'une fois prêt : on le relance chaque
            // seconde jusqu'à sa première réponse (comme le script officiel).
            listen(iframe) {
                let tries = 0;
                const ping = setInterval(() => {
                    if (iframe.dataset.ready || ! iframe.isConnected || ++tries > 20) return clearInterval(ping);
                    iframe.contentWindow?.postMessage(JSON.stringify({ event: 'listening', id: iframe.dataset.look, channel: 'widget' }), '*');
                }, 1000);
            },
            async share(url, title) {
                if (navigator.share) { navigator.share({ title, url }).catch(() => {}); return; }
                await navigator.clipboard?.writeText(url);
                this.copied = url;
                setTimeout(() => this.copied = null, 2500);
            },
        }"
        x-init="
            const sections = [...$refs.scroller.querySelectorAll('[data-section]')];
            // Le look à l'écran : sa vidéo joue, l'adresse le désigne.
            const onScreen = new IntersectionObserver((entries) => entries.forEach((entry) => {
                const video = entry.target.querySelector('video');
                if (entry.isIntersecting) {
                    active = +entry.target.dataset.section;
                    elapsed = 0;
                    progress = 0;
                    history.replaceState(null, '', entry.target.dataset.url);
                    if (video && ! still) { video.currentTime = 0; video.play().catch(() => {}); }
                } else if (video) {
                    video.pause();
                }
            }), { root: $refs.scroller, threshold: 0.6 });
            // Le voisin se prépare un écran à l'avance.
            const nearby = new IntersectionObserver((entries) => entries.forEach((entry) => {
                const video = entry.target.querySelector('video');
                if (entry.isIntersecting && video && video.preload === 'none' && ! still) video.preload = 'auto';
            }), { root: $refs.scroller, rootMargin: '50% 0px' });
            sections.forEach((section) => { onScreen.observe(section); nearby.observe(section); });
            // Panneau ouvert : la page derrière ne défile plus (une seule barre).
            $watch('sheet', (open) => document.documentElement.classList.toggle('overflow-hidden', open !== null));
            setInterval(() => tick(), 100);
            window.addEventListener('message', (event) => youtube(event));
            @if ($startIndex !== null && $startIndex !== false)
                $refs.scroller.querySelector('[data-section=\'{{ $startIndex }}\']')?.scrollIntoView();
            @endif
        "
        @keydown.window.arrow-down.prevent="sheet === null && go(1)"
        @keydown.window.arrow-up.prevent="sheet === null && go(-1)"
        @keydown.window.escape="sheet = null"
    >
        <div x-ref="scroller" class="h-svh snap-y snap-mandatory overflow-y-auto overscroll-y-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">

            {{-- OUVERTURE --}}
            <section data-section="-1" data-url="{{ route('shop.lookbook') }}" class="relative flex h-svh snap-start items-center justify-center overflow-hidden">
                @if ($cover?->image_path)
                    <img src="{{ $cover->sizedUrl('medium') }}" alt="" class="absolute inset-0 h-full w-full scale-110 object-cover opacity-70 blur-xl motion-safe:animate-[lookbook-breathe_14s_ease-in-out_infinite_alternate]">
                @endif
                <div class="absolute inset-0 bg-gradient-to-b from-brand-ink/50 via-brand-ink/30 to-brand-ink"></div>
                <x-shop.fold-backdrop :cut="false" class="opacity-40" />

                <div class="relative px-6 text-center">
                    <span class="text-xs uppercase tracking-[0.5em] text-brand-sage">Aissatou Store</span>
                    <h1 class="mt-6 font-display text-6xl italic leading-none sm:text-8xl lg:text-9xl">Lookbook</h1>
                    <p class="mx-auto mt-8 max-w-md font-display text-xl leading-snug text-brand-surface/80 sm:text-2xl">
                        « {{ $settings->about_quote ?: 'L\'élégance dans la pudeur.' }} »
                    </p>
                    @if ($total)
                        <p class="mt-6 text-xs uppercase tracking-[0.3em] text-brand-surface/50">{{ $total }} {{ $total > 1 ? 'tenues' : 'tenue' }}</p>
                    @else
                        <p class="mt-6 text-sm text-brand-surface/60">Les premiers looks arrivent bientôt.</p>
                    @endif
                </div>

                @if ($total)
                    <button type="button" @click="go(1)" class="absolute bottom-10 left-1/2 flex -translate-x-1/2 flex-col items-center gap-3 text-[0.65rem] uppercase tracking-[0.3em] text-brand-surface/60 transition hover:text-brand-surface">
                        Faites défiler
                        <svg class="h-5 w-5 motion-safe:animate-bounce" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                @endif
            </section>

            {{-- LES LOOKS --}}
            @foreach ($looks as $index => $look)
                @php
                    $pieces = $look->hotspots->pluck('product');
                    $lookUrl = route('shop.lookbook.look', $look);
                @endphp
                <section
                    id="{{ $look->slug }}"
                    data-section="{{ $index }}"
                    data-url="{{ $lookUrl }}"
                    class="relative h-svh snap-start overflow-hidden"
                    aria-label="{{ $look->title }}"
                >
                    {{-- Fond tiré de la même image (ordinateur). --}}
                    @if ($look->image_path)
                        <img src="{{ $look->sizedUrl('medium') }}" alt="" loading="lazy" class="absolute inset-0 hidden h-full w-full scale-110 object-cover opacity-50 blur-3xl lg:block">
                    @endif
                    <div class="absolute inset-0 hidden bg-brand-ink/50 lg:block"></div>

                    {{-- L'image : plein écran sur téléphone, colonne centrale sur ordinateur. --}}
                    @php $mediaClass = 'absolute inset-0 h-full w-full object-cover lg:inset-auto lg:left-1/2 lg:top-0 lg:w-auto lg:-translate-x-1/2 lg:aspect-[9/16] lg:shadow-2xl'; @endphp
                    @if ($look->isVideo())
                        <video
                            src="{{ $look->videoUrl() }}"
                            poster="{{ $look->sizedUrl('large') }}"
                            muted
                            playsinline
                            preload="none"
                            :controls="still"
                            @timeupdate="timeupdate($el, {{ $index }})"
                            @ended="ended($el, {{ $index }})"
                            aria-label="{{ $look->title }}"
                            class="{{ $mediaClass }}"
                        ></video>
                    @elseif ($look->isYouTube())
                        {{-- YouTube : l'image d'aperçu d'abord, le lecteur seulement
                             quand le look est à l'écran (il pèse plus lourd qu'une
                             vidéo envoyée). Lecteur agrandi pour couvrir le cadre
                             comme une vidéo, et inerte : le geste fait défiler les
                             looks, il n'ouvre pas les menus de YouTube. --}}
                        <div class="{{ $mediaClass }} overflow-hidden bg-brand-ink">
                            @if ($look->image_path)
                                <img src="{{ $look->sizedUrl('large') }}" alt="{{ $look->title }}" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                            @endif
                            <template x-if="active === {{ $index }} && ! still">
                                <iframe
                                    src="{{ \App\Support\YouTube::embedUrl($look->youtube_id, loop: false) }}"
                                    data-look="{{ $index }}"
                                    @load="listen($el)"
                                    title="{{ $look->title }}"
                                    allow="autoplay; encrypted-media; picture-in-picture"
                                    tabindex="-1"
                                    class="pointer-events-none absolute left-1/2 top-1/2 h-[max(100%,177.78vw)] w-[max(100%,56.25svh)] -translate-x-1/2 -translate-y-1/2 border-0 lg:h-full lg:w-full"
                                ></iframe>
                            </template>
                            {{-- Animations réduites / économie de données : lecture à la demande. --}}
                            <a
                                x-show="still"
                                x-cloak
                                href="{{ \App\Support\YouTube::watchUrl($look->youtube_id) }}"
                                target="_blank"
                                rel="noopener"
                                class="absolute inset-0 flex items-center justify-center"
                                aria-label="Regarder la vidéo « {{ $look->title }} » sur YouTube"
                            >
                                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-ink/60 backdrop-blur">
                                    <svg class="ml-1 h-7 w-7" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.04-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14z" /></svg>
                                </span>
                            </a>
                        </div>
                    @else
                        <div class="{{ $mediaClass }} overflow-hidden">
                            <img
                                src="{{ $look->sizedUrl('large') }}"
                                alt="{{ $look->title }}"
                                loading="lazy"
                                class="h-full w-full object-cover transition-transform duration-[8000ms] ease-out"
                                :class="active === {{ $index }} && 'motion-safe:scale-105'"
                            >
                        </div>
                    @endif

                    {{-- Lisibilité du texte sur téléphone. --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-brand-ink via-brand-ink/10 to-brand-ink/40 lg:hidden"></div>

                    {{-- Grand numéro en marge droite (ordinateur). --}}
                    <span
                        class="absolute right-24 top-1/2 hidden -translate-y-1/2 font-display text-[11rem] leading-none text-brand-surface/10 transition duration-1000 lg:block xl:right-32"
                        :class="active === {{ $index }} ? 'opacity-100' : 'opacity-0'"
                        aria-hidden="true"
                    >{{ $pad($index + 1) }}</span>

                    {{-- Le récit : en bas sur téléphone, en marge gauche sur ordinateur. --}}
                    <div
                        class="absolute inset-x-0 bottom-0 px-6 pb-10 transition duration-1000 ease-out sm:px-10 lg:inset-x-auto lg:bottom-auto lg:left-10 lg:top-1/2 lg:w-[calc(50%-28svh-4rem)] lg:-translate-y-1/2 lg:px-0 lg:pb-0 xl:left-16"
                        :class="active === {{ $index }} ? 'opacity-100 translate-y-0 lg:-translate-y-1/2' : 'opacity-0 translate-y-6 lg:-translate-y-[45%]'"
                        style="transition-delay: 250ms"
                    >
                        <p class="text-[0.65rem] uppercase tracking-[0.3em] text-brand-sage">
                            {{ $look->chapter ? $look->chapter.' · ' : '' }}{{ $pad($index + 1) }} / {{ $pad($total) }}
                        </p>
                        <h2 class="mt-4 font-display text-5xl leading-[1.02] sm:text-6xl xl:text-7xl">{{ $look->title }}</h2>
                        @if ($look->mood || $look->subtitle)
                            <p class="mt-5 max-w-sm text-base leading-relaxed text-brand-surface/75">{{ $look->mood ?: $look->subtitle }}</p>
                        @endif

                        <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-4">
                            @if ($pieces->isNotEmpty())
                                <button
                                    type="button"
                                    @click="sheet = {{ $index }}"
                                    class="inline-flex items-center gap-3 rounded-sm border border-brand-surface/40 px-6 py-3 text-xs font-medium uppercase tracking-[0.18em] transition hover:border-brand-surface hover:bg-brand-surface hover:text-brand-ink"
                                >
                                    Les pièces du look
                                    <span class="text-brand-surface/60">{{ $pieces->count() }}</span>
                                </button>
                            @endif
                            <button type="button" @click="share(@js($lookUrl), @js($look->title))" class="text-xs uppercase tracking-[0.18em] text-brand-surface/60 underline-offset-4 transition hover:text-brand-surface hover:underline">
                                <span x-text="copied === @js($lookUrl) ? 'Lien copié' : 'Partager'">Partager</span>
                            </button>
                        </div>
                    </div>
                </section>
            @endforeach

            {{-- FIN --}}
            @if ($total)
                <section data-section="{{ $total }}" data-url="{{ route('shop.lookbook') }}" class="relative flex h-svh snap-start items-center justify-center overflow-hidden">
                    <x-shop.fold-backdrop :cut="false" class="opacity-50" />
                    <div class="relative px-6 text-center">
                        <x-shop.signature variant="vertical" :with-slogan="false" class="justify-center" />
                        <p class="mx-auto mt-10 max-w-md font-display text-3xl leading-snug sm:text-4xl">Chaque pièce se porte aussi seule, ou autrement.</p>
                        <div class="mt-10 flex flex-col items-center gap-5 sm:flex-row sm:justify-center">
                            <x-shop.cta href="{{ route('shop.index') }}" variant="primary">Découvrir la collection</x-shop.cta>
                            @if ($whatsapp)
                                <x-shop.cta
                                    href="{{ route('shop.whatsapp', ['texte' => 'Bonjour, j\'ai vu le Lookbook et j\'aimerais composer une tenue avec une conseillère.']) }}"
                                    target="_blank"
                                    rel="noopener"
                                    :on-dark="true"
                                >Composer ma tenue avec une conseillère</x-shop.cta>
                            @endif
                        </div>
                        <button type="button" @click="$refs.scroller.scrollTo({ top: 0, behavior: 'smooth' })" class="mt-12 text-xs uppercase tracking-[0.3em] text-brand-surface/50 transition hover:text-brand-surface">
                            Revoir depuis le début
                        </button>
                    </div>
                </section>
            @endif
        </div>

        {{-- Navigation à droite : look précédent / suivant (le grand numéro du
             look, en marge, dit déjà où l'on en est). --}}
        @if ($total)
            <nav class="absolute right-3 top-1/2 z-10 flex -translate-y-1/2 flex-col items-center gap-3 sm:right-5 lg:right-8" aria-label="Passer d'un look à l'autre">
                <button
                    type="button"
                    @click="go(-1)"
                    :disabled="active <= -1"
                    class="flex h-12 w-12 items-center justify-center rounded-full border border-brand-surface/50 bg-brand-ink/40 text-brand-surface shadow-lg backdrop-blur transition hover:scale-105 hover:border-brand-surface hover:bg-brand-surface hover:text-brand-ink disabled:pointer-events-none disabled:opacity-25 lg:h-16 lg:w-16"
                    aria-label="Look précédent"
                >
                    <svg class="h-5 w-5 lg:h-6 lg:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" /></svg>
                </button>


                {{-- Pause du défilement automatique (la vidéo tourne alors en boucle). --}}
                <button
                    type="button"
                    x-show="! still"
                    @click="auto = ! auto"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-brand-surface/70 transition hover:text-brand-surface lg:h-11 lg:w-11"
                    :aria-label="auto ? 'Mettre en pause le défilement' : 'Reprendre le défilement'"
                    :aria-pressed="(! auto).toString()"
                >
                    <svg x-show="auto" class="h-4 w-4 lg:h-5 lg:w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z" /></svg>
                    <svg x-show="! auto" x-cloak class="ml-0.5 h-4 w-4 lg:h-5 lg:w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.04-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14z" /></svg>
                </button>

                <button
                    type="button"
                    @click="go(1)"
                    :disabled="active >= {{ $total }}"
                    class="flex h-12 w-12 items-center justify-center rounded-full border border-brand-surface/50 bg-brand-ink/40 text-brand-surface shadow-lg backdrop-blur transition hover:scale-105 hover:border-brand-surface hover:bg-brand-surface hover:text-brand-ink disabled:pointer-events-none disabled:opacity-25 lg:h-16 lg:w-16"
                    aria-label="Look suivant"
                >
                    <svg class="h-5 w-5 lg:h-6 lg:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                </button>
            </nav>
        @endif

        {{-- Temps restant avant le look suivant. --}}
        <div x-show="! still && active < total" class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-0.5 bg-brand-surface/15" aria-hidden="true">
            <div class="h-full origin-left bg-brand-surface/80 transition-transform duration-100 ease-linear" :class="! auto && 'opacity-40'" :style="`transform: scaleX(${progress})`"></div>
        </div>

        {{-- PANNEAU DES PIÈCES — bas de l'écran sur téléphone, côté droit sur ordinateur. --}}
        <div x-show="sheet !== null" x-cloak class="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="Les pièces du look">
            <div
                x-show="sheet !== null"
                x-transition.opacity.duration.300ms
                class="absolute inset-0 bg-brand-ink/60 backdrop-blur-sm"
                @click="sheet = null"
            ></div>

            @foreach ($looks as $index => $look)
                @php $pieces = $look->hotspots->pluck('product'); @endphp
                @continue($pieces->isEmpty())
                <div
                    x-show="sheet === {{ $index }}"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="translate-y-full lg:translate-y-0 lg:translate-x-full"
                    x-transition:enter-end="translate-y-0 lg:translate-x-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="translate-y-0 lg:translate-x-0"
                    x-transition:leave-end="translate-y-full lg:translate-y-0 lg:translate-x-full"
                    class="absolute inset-x-0 bottom-0 flex max-h-[85svh] flex-col rounded-t-xl bg-brand-surface text-brand-ink shadow-2xl lg:inset-y-0 lg:left-auto lg:right-0 lg:max-h-none lg:w-[28rem] lg:rounded-none"
                >
                    <div class="flex items-start justify-between gap-4 border-b border-brand-ink/10 px-6 py-5">
                        <div>
                            <p class="text-[0.65rem] uppercase tracking-[0.3em] text-brand-muted">Les pièces du look</p>
                            <p class="mt-1 font-display text-2xl">{{ $look->title }}</p>
                        </div>
                        <button type="button" @click="sheet = null" class="p-1 text-brand-muted transition hover:text-brand-ink" aria-label="Fermer">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <ul class="flex-1 divide-y divide-brand-ink/10 overflow-y-auto overscroll-contain px-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        @foreach ($pieces as $piece)
                            <li class="flex gap-4 py-5">
                                <a href="{{ route('shop.product', $piece) }}" class="h-24 w-20 flex-shrink-0 overflow-hidden rounded-sm bg-brand-parchment">
                                    @if ($thumb = $piece->primaryImage())
                                        <img src="{{ $thumb->sizedUrl('thumb') }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    @endif
                                </a>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[0.6rem] uppercase tracking-[0.2em] text-brand-muted">{{ $piece->category->name }}</p>
                                    <a href="{{ route('shop.product', $piece) }}" class="mt-0.5 block font-display text-lg leading-snug hover:text-brand-signature">{{ $piece->name }}</a>
                                    <p class="text-sm text-brand-muted">{{ $fcfa($piece->price) }}</p>

                                    {{-- Ajout au panier : retour ici, panier ouvert. --}}
                                    <form method="POST" action="{{ route('shop.cart.store') }}" class="mt-3 flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $piece->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        @if ($piece->variants->isNotEmpty())
                                            <select name="variant_id" required class="rounded-sm border-brand-ink/20 py-1.5 pl-2 pr-8 text-sm focus:border-brand-signature focus:ring-brand-signature" aria-label="Choix pour {{ $piece->name }}">
                                                @foreach ($piece->variants as $variant)
                                                    <option value="{{ $variant->id }}" @disabled($variant->stock < 1)>
                                                        {{ collect($variant->attributes ?? [])->implode(', ') ?: 'Modèle unique' }}{{ $variant->stock < 1 ? ' — épuisé' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <button type="submit" class="rounded-sm bg-brand-ink px-4 py-2 text-[0.65rem] font-medium uppercase tracking-[0.15em] text-brand-surface transition hover:bg-brand-signature">
                                            Ajouter au panier
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <div class="border-t border-brand-ink/10 px-6 py-5">
                        @if ($pieces->count() > 1)
                            <p class="flex items-baseline justify-between text-xs uppercase tracking-[0.2em] text-brand-muted">
                                <span>La tenue complète</span>
                                <span class="font-display text-2xl normal-case tracking-normal text-brand-ink">{{ $fcfa($pieces->sum('price')) }}</span>
                            </p>
                        @endif
                        @if ($whatsapp)
                            <a
                                href="{{ route('shop.whatsapp', ['texte' => "Bonjour, j'aimerais commander le look « {$look->title} » :\n".$pieces->map(fn ($piece) => '- '.$piece->name)->implode("\n")."\n".route('shop.lookbook.look', $look)]) }}"
                                target="_blank"
                                rel="noopener"
                                class="mt-4 flex w-full items-center justify-center rounded-sm bg-brand-accent px-6 py-3.5 text-xs font-medium uppercase tracking-[0.15em] text-brand-surface transition hover:bg-brand-signature"
                            >
                                Commander ce look sur WhatsApp
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-shop-layout>

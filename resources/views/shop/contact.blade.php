<x-shop-layout :title="'Contact — '.config('app.name')">
    {{-- CONTACT — trois canaux mis en scène sur fond Encre (WhatsApp en tête :
         c'est le plus rapide au Sénégal), puis le formulaire, les horaires et
         les réseaux. Tout vient des réglages du site (Filament > Contenu du
         site > Réglages du site). --}}
    @php
        $channels = array_values(array_filter([
            $settings->contact_whatsapp ? [
                'icon' => 'whatsapp',
                'label' => 'WhatsApp',
                'value' => $settings->contact_whatsapp,
                'action' => 'Écrire sur WhatsApp',
                'href' => route('shop.whatsapp'),
                'external' => true,
                'badge' => 'Le plus rapide',
            ] : null,
            $settings->contact_email ? [
                'icon' => 'mail',
                'label' => 'E-mail',
                'value' => $settings->contact_email,
                'action' => 'Nous écrire',
                'href' => 'mailto:'.$settings->contact_email,
                'external' => false,
                'badge' => null,
            ] : null,
            $settings->contact_address ? [
                'icon' => 'pin',
                'label' => 'La maison',
                'value' => $settings->contact_address,
                'action' => null,
                'href' => null,
                'external' => false,
                'badge' => null,
            ] : null,
        ]));

        $socials = array_values(array_filter([
            $settings->social_instagram ? ['network' => 'instagram', 'label' => 'Instagram', 'hint' => 'Nos pièces, portées', 'url' => $settings->social_instagram] : null,
            $settings->social_facebook ? ['network' => 'facebook', 'label' => 'Facebook', 'hint' => 'Les nouvelles de la maison', 'url' => $settings->social_facebook] : null,
            $settings->social_tiktok ? ['network' => 'tiktok', 'label' => 'TikTok', 'hint' => 'Tutoriels et tombés', 'url' => $settings->social_tiktok] : null,
        ]));

        $icons = [
            'mail' => 'M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75',
            'pin' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
        ];
    @endphp

    {{-- EN-TÊTE + CANAUX --}}
    <section class="relative overflow-hidden bg-brand-ink text-brand-surface">
        <x-shop.fold-backdrop :cut="false" class="opacity-80" />

        <div class="relative max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 pt-16 pb-20 sm:pt-24 sm:pb-28">
            <div class="max-w-2xl" data-reveal>
                <span class="text-xs uppercase tracking-[0.3em] text-brand-sage">Une question ?</span>
                <h1 class="mt-4 font-display text-5xl sm:text-7xl leading-[1]">Parlons-en</h1>
                <p class="mt-6 text-lg leading-relaxed text-brand-surface/70">Une pièce, une taille, une commande, une idée cadeau : une conseillère vous répond, avec plaisir.</p>
            </div>

            @if ($channels)
                <div @class(['mt-14 grid gap-4 sm:gap-6', 'md:grid-cols-3' => count($channels) === 3, 'md:grid-cols-2' => count($channels) === 2])>
                    @foreach ($channels as $channel)
                        @php $tag = $channel['href'] ? 'a' : 'div'; @endphp
                        <{{ $tag }}
                            @if ($channel['href']) href="{{ $channel['href'] }}" @endif
                            @if ($channel['external']) target="_blank" rel="noopener" data-whatsapp-cta @endif
                            @class([
                                'group relative flex flex-col border p-6 sm:p-8 backdrop-blur-sm transition duration-500',
                                'border-brand-surface/25 bg-brand-signature/40 hover:bg-brand-signature/60' => $loop->first && $channel['badge'],
                                'border-brand-surface/10 bg-brand-surface/[0.04]' => ! ($loop->first && $channel['badge']),
                                'hover:border-brand-surface/30 hover:bg-brand-surface/[0.08]' => $channel['href'] && ! ($loop->first && $channel['badge']),
                            ])
                            data-reveal
                            style="--reveal-delay: {{ 120 + $loop->index * 110 }}ms"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <span class="flex h-12 w-12 items-center justify-center rounded-full border border-brand-surface/20 text-brand-surface transition duration-500 group-hover:scale-110">
                                    @if ($channel['icon'] === 'whatsapp')
                                        <x-shop.social-icon network="whatsapp" class="h-5 w-5" />
                                    @else
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$channel['icon']] }}" />
                                        </svg>
                                    @endif
                                </span>
                                @if ($channel['badge'])
                                    <span class="border border-brand-parchment/40 px-2.5 py-1 text-[0.6rem] uppercase tracking-[0.2em] text-brand-parchment">{{ $channel['badge'] }}</span>
                                @endif
                            </div>

                            <span class="mt-8 text-[0.65rem] uppercase tracking-[0.3em] text-brand-surface/60">{{ $channel['label'] }}</span>
                            <span class="mt-2 font-display text-2xl break-words">{{ $channel['value'] }}</span>

                            @if ($channel['action'])
                                <span class="mt-6 inline-flex items-center gap-2 text-xs uppercase tracking-[0.2em] text-brand-surface/80 transition group-hover:text-brand-surface">
                                    {{ $channel['action'] }}
                                    <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                    </svg>
                                </span>
                            @endif
                        </{{ $tag }}>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 sm:py-24 grid gap-12 lg:grid-cols-[minmax(0,1.3fr),minmax(0,1fr)] lg:gap-20">
        {{-- FORMULAIRE --}}
        <div class="relative" data-reveal>
            <div class="passage-frame pointer-events-none absolute -inset-3 sm:-inset-4"></div>
            <div class="relative border border-brand-ink/10 bg-white p-6 sm:p-10">
                <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Écrivez-nous</span>
                <h2 class="mt-3 font-display text-3xl text-brand-ink">Un message, une réponse</h2>
                <p class="mt-2 text-sm text-brand-muted">Laissez-nous quelques mots : nous vous répondons par e-mail.</p>

                @if (session('status') === 'message-sent')
                    <p class="mt-6 flex items-center gap-3 border-l-2 border-brand-signature bg-brand-parchment/60 px-4 py-3 text-sm text-brand-ink">
                        <svg class="h-5 w-5 shrink-0 text-brand-signature" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Merci, votre message est bien arrivé. Nous vous répondons rapidement.
                    </p>
                @endif

                <form method="post" action="{{ route('shop.contact.store') }}" class="mt-8 space-y-6">
                    @csrf

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="name" value="Votre nom" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autocomplete="name" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Votre e-mail" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autocomplete="email" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>
                    </div>

                    <div x-data="{ count: {{ mb_strlen(old('message', '')) }} }">
                        <x-input-label for="message" value="Votre message" />
                        <textarea
                            id="message"
                            name="message"
                            rows="6"
                            required
                            maxlength="2000"
                            placeholder="Une question sur une pièce, une commande, une livraison…"
                            @input="count = $event.target.value.length"
                            class="mt-1 block w-full rounded-sm border-brand-ink/20 text-sm focus:border-brand-signature focus:ring-brand-accent"
                        >{{ old('message') }}</textarea>
                        <div class="mt-1 flex items-center justify-between">
                            <x-input-error :messages="$errors->get('message')" />
                            <span class="ml-auto text-xs text-brand-muted"><span x-text="count">0</span>/2000</span>
                        </div>
                    </div>

                    <x-shop.cta variant="primary" class="inline-flex items-center gap-3">
                        Envoyer le message
                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                        </svg>
                    </x-shop.cta>
                </form>
            </div>
        </div>

        <aside class="space-y-10" data-reveal style="--reveal-delay: 150ms">
            {{-- HORAIRES --}}
            @if ($settings->contact_hours)
                <div class="border-l-2 border-brand-signature pl-6">
                    <span class="flex items-center gap-3 text-xs uppercase tracking-[0.3em] text-brand-signature">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Horaires &amp; livraison
                    </span>
                    <p class="mt-4 leading-relaxed text-brand-ink/80">{{ $settings->contact_hours }}</p>
                </div>
            @endif

            {{-- RÉSEAUX --}}
            @if ($socials)
                <div>
                    <span class="text-xs uppercase tracking-[0.3em] text-brand-signature">Suivez la maison</span>
                    <h2 class="mt-3 font-display text-3xl text-brand-ink">Sur les réseaux</h2>

                    <ul class="mt-6 space-y-3">
                        @foreach ($socials as $social)
                            <li>
                                <a href="{{ $social['url'] }}" target="_blank" rel="noopener" class="group flex items-center gap-5 border border-brand-ink/10 bg-white p-4 sm:p-5 transition duration-500 hover:border-brand-signature hover:bg-brand-signature hover:text-brand-surface">
                                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-parchment text-brand-signature transition duration-500 group-hover:scale-110 group-hover:bg-brand-surface/15 group-hover:text-brand-surface">
                                        <x-shop.social-icon :network="$social['network']" class="h-5 w-5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-display text-xl text-brand-ink transition group-hover:text-brand-surface">{{ $social['label'] }}</span>
                                        <span class="block text-sm text-brand-muted transition group-hover:text-brand-surface/70">{{ $social['hint'] }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-2 text-[0.65rem] uppercase tracking-[0.2em] text-brand-ink/60 transition group-hover:text-brand-surface">
                                        Suivre
                                        <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-shop-layout>

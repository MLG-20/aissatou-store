<x-shop-layout :title="'Contact — '.config('app.name')">
    <section class="w-full bg-gradient-to-br from-brand-parchment via-brand-surface to-brand-muted/20 border-b border-brand-ink/10 py-14 sm:py-20">
        <div class="max-w-shell mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
            <span class="text-xs uppercase tracking-[0.2em] text-brand-signature">Une question ?</span>
            <h1 class="font-display text-4xl sm:text-5xl text-brand-ink mt-1">Contactez-nous</h1>
            <p class="mt-3 text-brand-muted max-w-xl">Une question sur un produit, une commande, une livraison ? Écrivez-nous, nous répondons rapidement.</p>
        </div>
    </section>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-16 grid md:grid-cols-2 gap-12">
        <div>
            <h2 class="font-display text-xl text-brand-ink mb-6">Écrivez-nous</h2>

            @if (session('status') === 'message-sent')
                <p class="text-sm text-green-700 mb-4">Votre message a bien été envoyé, merci ! Nous vous répondrons rapidement.</p>
            @endif

            <form method="post" action="{{ route('shop.contact.store') }}" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="name" value="Nom" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-input-label for="message" value="Message" />
                    <textarea
                        id="message"
                        name="message"
                        rows="5"
                        required
                        class="mt-1 block w-full rounded-sm border-brand-ink/20 focus:border-brand-signature focus:ring-brand-accent text-sm"
                    >{{ old('message') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('message')" />
                </div>

                <x-primary-button>Envoyer</x-primary-button>
            </form>
        </div>

        <div class="space-y-8">
            <div>
                <h2 class="font-display text-xl text-brand-ink mb-4">Coordonnées directes</h2>
                <ul class="space-y-3 text-brand-ink/80">
                    <li class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand-signature shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                        </svg>
                        {{-- Numéro cliquable vers WhatsApp : seuls les chiffres
                             sont conservés (wa.me exige un format international
                             sans « + » ni espaces). --}}
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', $settings->contact_whatsapp) }}" target="_blank" rel="noopener" class="underline hover:text-brand-signature">
                            WhatsApp : {{ $settings->contact_whatsapp }}
                        </a>
                    </li>
                    <li class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand-signature shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                        {{ $settings->contact_email }}
                    </li>
                    <li class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-brand-signature shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                        {{ $settings->contact_address }}
                    </li>
                </ul>
            </div>

            <div>
                <h2 class="font-display text-xl text-brand-ink mb-4">Horaires &amp; livraison</h2>
                <p class="text-brand-ink/80 text-sm leading-relaxed">
                    {{ $settings->contact_hours }}
                </p>
            </div>

            @if ($settings->social_instagram || $settings->social_facebook || $settings->social_tiktok)
                <div>
                    <h2 class="font-display text-xl text-brand-ink mb-4">Suivez-nous</h2>
                    <ul class="flex flex-wrap gap-4 text-sm">
                        @foreach (['social_instagram' => 'Instagram', 'social_facebook' => 'Facebook', 'social_tiktok' => 'TikTok'] as $field => $label)
                            @if ($settings->$field)
                                <li>
                                    <a href="{{ $settings->$field }}" target="_blank" rel="noopener" class="underline text-brand-ink hover:text-brand-signature">
                                        {{ $label }} →
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-shop-layout>

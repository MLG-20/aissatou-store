<x-shop-layout :title="'Commande — '.config('app.name')">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12 py-8">
        <h1 class="font-display text-3xl mb-6">Votre commande</h1>

        @if ($message = collect($errors->getMessages())->except('promo_code')->flatten()->first())
            <p class="mb-6 text-sm text-brand-signature">{{ $message }}</p>
        @endif

        {{-- Code promo : formulaire distinct (pas de formulaires imbriqués).
             Aperçu seulement : la remise est recalculée et revalidée côté
             serveur à la validation de la commande. --}}
        <section class="mb-8 rounded-sm border border-brand-ink/10 bg-white p-4">
            @if ($promo)
                <div class="flex items-center justify-between gap-4">
                    <p class="text-sm text-brand-ink">
                        Code <span class="font-medium">{{ $promo->code->code }}</span> appliqué :
                        <span class="font-medium">-{{ number_format($promo->discount, 0, ',', ' ') }} FCFA</span>
                    </p>
                    <form method="POST" action="{{ route('shop.checkout.promo.remove') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm underline text-brand-muted hover:text-brand-signature">Retirer</button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('shop.checkout.promo.apply') }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                    @csrf
                    <label class="flex-1">
                        <span class="block text-sm text-brand-ink mb-1">Code promo</span>
                        <input
                            type="text"
                            name="promo_code"
                            value="{{ old('promo_code') }}"
                            maxlength="40"
                            autocomplete="off"
                            class="w-full rounded-sm border-brand-ink/20 uppercase focus:border-brand-signature focus:ring-brand-signature"
                        >
                    </label>
                    <button type="submit" class="px-6 py-2.5 text-sm uppercase tracking-wide border border-brand-ink/30 rounded-sm hover:border-brand-ink">
                        Appliquer
                    </button>
                </form>
            @endif
            @error('promo_code')
                <p class="mt-2 text-sm text-brand-signature">{{ $message }}</p>
            @enderror
            @if (session('promo_error'))
                <p class="mt-2 text-sm text-brand-signature">{{ session('promo_error') }}</p>
            @endif
        </section>

        <form
            method="POST"
            action="{{ route('shop.checkout.store') }}"
            class="space-y-8"
            x-data="{
                subtotal: {{ $cart->items->sum(fn ($item) => $item->lineTotal()) }},
                discount: {{ $promo?->discount ?? 0 }},
                fee: {{ ($fees[$addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id] ?? ['fee' => 0])['fee'] }},
                zone: '{{ addslashes(($fees[$addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id] ?? ['label' => ''])['label']) }}',
                fmt(n) { return new Intl.NumberFormat('fr-FR', {maximumFractionDigits: 0}).format(n) + ' FCFA'; }
            }"
        >
            @csrf

            <section>
                <h2 class="font-display text-xl mb-4">Récapitulatif</h2>
                <div class="divide-y divide-brand-ink/10 border-y border-brand-ink/10">
                    @foreach ($cart->items as $item)
                        <div class="py-4 flex items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-brand-ink">{{ $item->product->name }}</p>
                                @if ($variantLabel = collect($item->variant?->attributes ?? [])->map(fn ($value, $key) => ucfirst($key).' : '.$value)->implode(', '))
                                    <p class="text-xs text-brand-muted mt-0.5">{{ $variantLabel }}</p>
                                @endif
                                <p class="text-xs text-brand-muted mt-0.5">Quantité : {{ $item->quantity }}</p>
                            </div>
                            <p class="text-sm text-brand-ink whitespace-nowrap">
                                {{ number_format($item->lineTotal(), 0, ',', ' ') }} FCFA
                            </p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-1 text-right">
                    <p class="text-sm text-brand-muted">Sous-total : <span x-text="fmt(subtotal)"></span></p>
                    @if ($promo)
                        <p class="text-sm text-brand-signature">Remise ({{ $promo->code->code }}) : -<span x-text="fmt(discount)"></span></p>
                    @endif
                    <p class="text-sm text-brand-muted">Livraison (<span x-text="zone"></span>) : <span x-text="fmt(fee)"></span></p>
                    <p class="font-display text-xl">Total : <span x-text="fmt(subtotal - discount + fee)"></span></p>
                </div>
            </section>

            <section>
                <h2 class="font-display text-xl mb-4">Adresse de livraison</h2>
                @if ($addresses->isEmpty())
                    <p class="text-sm text-brand-muted">
                        Ajoutez d'abord une adresse depuis
                        <a href="{{ route('account.addresses.index') }}" class="underline hover:text-brand-signature">votre carnet d'adresses</a>.
                    </p>
                @else
                    <div class="space-y-3">
                        @foreach ($addresses as $address)
                            <label class="flex items-start gap-3 rounded-sm border border-brand-ink/10 bg-white p-4 cursor-pointer has-checked:border-brand-signature">
                                <input
                                    type="radio"
                                    name="address_id"
                                    value="{{ $address->id }}"
                                    @checked($address->is_default)
                                    data-fee="{{ $fees[$address->id]['fee'] }}"
                                    data-zone="{{ $fees[$address->id]['label'] }}"
                                    @change="fee = +$el.dataset.fee; zone = $el.dataset.zone"
                                    class="mt-1 accent-brand-signature"
                                >
                                <span>
                                    <span class="block text-sm text-brand-ink">{{ $address->recipient_name }} — {{ $address->phone }}</span>
                                    <span class="block text-sm text-brand-muted">{{ $address->address_line }}, {{ $address->city }}</span>
                                    @if ($address->is_default)
                                        <span class="mt-1 inline-block text-xs uppercase tracking-wide text-brand-signature">Par défaut</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <h2 class="font-display text-xl mb-4">Paiement</h2>
                <div class="space-y-3">
                    @foreach ($providers as $provider)
                        <label class="flex items-start gap-3 rounded-sm border border-brand-ink/10 bg-white p-4 cursor-pointer has-checked:border-brand-signature">
                            <input
                                type="radio"
                                name="provider"
                                value="{{ $provider->key() }}"
                                @checked($loop->first)
                                class="mt-1 accent-brand-signature"
                            >
                            <span class="text-sm text-brand-ink">{{ $provider->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            <x-shop.cta type="submit" variant="primary" class="w-full justify-center">
                Confirmer la commande
            </x-shop.cta>
        </form>
    </div>
</x-shop-layout>

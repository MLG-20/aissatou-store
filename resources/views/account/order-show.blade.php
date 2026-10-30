<x-account-layout :title="'Commande '.$order->number">
    <div class="max-w-3xl">
        <a href="{{ route('account.orders.index') }}" class="text-sm underline text-brand-muted hover:text-brand-signature">
            ← Retour à mes commandes
        </a>

        <h1 class="font-display text-3xl mt-4">Commande {{ $order->number }}</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Passée le {{ $order->created_at->format('d/m/Y à H:i') }} — statut : {{ $order->status->label() }}
        </p>

        @php
            $steps = [
                \App\Enums\Order\OrderStatus::New,
                \App\Enums\Order\OrderStatus::Preparing,
                \App\Enums\Order\OrderStatus::Shipped,
                \App\Enums\Order\OrderStatus::Delivered,
            ];
            $currentIndex = array_search($order->status, $steps, true);
        @endphp

        @if ($order->status === \App\Enums\Order\OrderStatus::Cancelled)
            <p class="mt-6 rounded-sm border border-brand-signature/40 bg-brand-parchment px-4 py-3 text-sm text-brand-ink">
                Cette commande a été annulée. Contactez-nous si vous avez une question.
            </p>
        @else
            <ol class="mt-6 grid grid-cols-4 gap-1 text-center">
                @foreach ($steps as $index => $step)
                    <li class="border-t-2 pt-2 {{ $index <= $currentIndex ? 'border-brand-signature' : 'border-brand-ink/10' }}">
                        <span class="block text-[11px] uppercase tracking-wide {{ $index <= $currentIndex ? 'text-brand-ink' : 'text-brand-muted' }}">
                            {{ $step->label() }}
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($order->delivery_assignee || $order->delivery_note)
            <div class="mt-6 rounded-sm border border-brand-ink/10 bg-white p-5">
                <h2 class="font-display text-lg">Suivi de livraison</h2>
                @if ($order->delivery_assignee)
                    <p class="mt-2 text-sm text-brand-ink">Livré par : {{ $order->delivery_assignee }}</p>
                @endif
                @if ($order->delivery_note)
                    <p class="mt-1 text-sm text-brand-muted">{{ $order->delivery_note }}</p>
                @endif
            </div>
        @endif

        <div class="mt-8 divide-y divide-brand-ink/10 border-y border-brand-ink/10">
            @foreach ($order->items as $item)
                <div class="py-4 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-brand-ink">{{ $item->product_name }}</p>
                        @if ($item->variant_label)
                            <p class="text-xs text-brand-muted mt-0.5">{{ $item->variant_label }}</p>
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
            <p class="text-sm text-brand-muted">Sous-total : {{ number_format($order->subtotal, 0, ',', ' ') }} FCFA</p>
            @if ($order->discount_total > 0)
                <p class="text-sm text-brand-signature">Remise ({{ $order->promo_code }}) : -{{ number_format($order->discount_total, 0, ',', ' ') }} FCFA</p>
            @endif
            <p class="text-sm text-brand-muted">Livraison{{ $order->shipping_zone ? ' ('.$order->shipping_zone.')' : '' }} : {{ number_format($order->shipping_total, 0, ',', ' ') }} FCFA</p>
            <p class="font-display text-xl">Total : {{ number_format($order->total, 0, ',', ' ') }} FCFA</p>
        </div>

        @if ($order->payment)
            @php
                $payment = $order->payment;
                $awaitingOnline = $payment->isOnline()
                    && $order->status === \App\Enums\Order\OrderStatus::New
                    && ! in_array($payment->status, [\App\Enums\Order\PaymentStatus::Confirmed, \App\Enums\Order\PaymentStatus::Refunded], true);
            @endphp
            <div class="mt-8 rounded-sm border border-brand-ink/10 bg-white p-5">
                <h2 class="font-display text-lg">Paiement</h2>
                <p class="mt-2 text-sm text-brand-ink">
                    {{ $payment->method ?? $payment->providerLabel() }} — {{ $payment->status->label() }}
                    @if ($payment->confirmed_at)
                        <span class="text-brand-muted">(le {{ $payment->confirmed_at->format('d/m/Y à H:i') }})</span>
                    @endif
                </p>
                @if ($awaitingOnline)
                    <a href="{{ route('shop.checkout.payment', $order->number) }}" class="mt-3 inline-block text-sm underline text-brand-signature hover:text-brand-accent">
                        Finaliser le paiement
                    </a>
                @endif
            </div>
        @endif

        <div class="mt-4 rounded-sm border border-brand-ink/10 bg-white p-5">
            <h2 class="font-display text-lg">Livraison</h2>
            <p class="mt-2 text-sm text-brand-ink">
                {{ $order->recipient_name }} — {{ $order->phone }}<br>
                {{ $order->address_line }}, {{ $order->city }}
                @if ($order->landmark)
                    <br>{{ $order->landmark }}
                @endif
            </p>
            @if ($order->hasLocation())
                <p class="mt-2 text-sm text-brand-muted">📍 Position exacte transmise au livreur</p>
            @endif
        </div>

        {{-- Le n° de commande part pré-rempli : la boutique retrouve la
             commande sans avoir à le demander. --}}
        @if (filled(\App\Models\Content\SiteSetting::current()->contact_whatsapp))
            <a
                href="{{ route('shop.whatsapp', ['texte' => 'Bonjour, j\'ai une question sur ma commande '.$order->number.'.']) }}"
                target="_blank"
                rel="noopener"
                class="mt-6 inline-block text-sm underline text-brand-muted hover:text-brand-signature"
            >
                Une question sur cette commande ? Écrivez-nous sur WhatsApp
            </a>
        @endif
    </div>
</x-account-layout>

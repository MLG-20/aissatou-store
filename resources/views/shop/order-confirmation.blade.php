<x-shop-layout :title="'Merci — '.config('app.name')">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 py-12 text-center">
        <p class="text-xs uppercase tracking-[0.25em] text-brand-signature">Commande confirmée</p>
        <h1 class="font-display text-3xl mt-3">Merci, à très vite !</h1>
        <p class="mt-4 text-brand-muted">
            Votre commande <span class="text-brand-ink font-medium">{{ $order->number }}</span>
            d'un montant de <span class="text-brand-ink font-medium">{{ number_format($order->total, 0, ',', ' ') }} FCFA</span>
            est bien enregistrée. Vous la payerez à la livraison, à l'adresse suivante :
        </p>
        <p class="mt-2 text-sm text-brand-ink">
            {{ $order->recipient_name }} — {{ $order->phone }}<br>
            {{ $order->address_line }}, {{ $order->city }}
        </p>

        <div class="mt-8 text-left divide-y divide-brand-ink/10 border-y border-brand-ink/10">
            @foreach ($order->items as $item)
                <div class="py-3 flex items-center gap-4">
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
            <p class="text-sm text-brand-muted">Livraison{{ $order->shipping_zone ? ' ('.$order->shipping_zone.')' : '' }} : {{ number_format($order->shipping_total, 0, ',', ' ') }} FCFA</p>
            <p class="font-display text-xl">Total : {{ number_format($order->total, 0, ',', ' ') }} FCFA</p>
        </div>

        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <x-shop.cta href="{{ route('shop.index') }}" variant="primary">Continuer mes achats</x-shop.cta>
            <a href="{{ route('account.orders.index') }}" class="inline-flex items-center justify-center px-6 py-3 text-sm uppercase tracking-wide border border-brand-ink/20 rounded-sm hover:border-brand-signature hover:text-brand-signature">
                Suivre mes commandes
            </a>
        </div>
    </div>
</x-shop-layout>

<x-shop-layout :title="'Commande '.$order->number.' — '.config('app.name')">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
        <a href="{{ route('account.orders.index') }}" class="text-sm underline text-brand-muted hover:text-brand-signature">
            ← Retour à mes commandes
        </a>

        <h1 class="font-display text-3xl mt-4">Commande {{ $order->number }}</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Passée le {{ $order->created_at->format('d/m/Y à H:i') }} — statut : {{ $order->status->label() }}
        </p>

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

        <p class="mt-4 text-right font-display text-xl">
            Total : {{ number_format($order->total, 0, ',', ' ') }} FCFA
        </p>

        <div class="mt-8 rounded-sm border border-brand-ink/10 bg-white p-5">
            <h2 class="font-display text-lg">Livraison</h2>
            <p class="mt-2 text-sm text-brand-ink">
                {{ $order->recipient_name }} — {{ $order->phone }}<br>
                {{ $order->address_line }}, {{ $order->city }}
                @if ($order->landmark)
                    <br>{{ $order->landmark }}
                @endif
            </p>
        </div>
    </div>
</x-shop-layout>

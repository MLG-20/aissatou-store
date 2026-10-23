<x-shop-layout :title="'Paiement — '.config('app.name')">
    @php($cancelled = $order->status === \App\Enums\Order\OrderStatus::Cancelled)
    @php($processing = $payment->status === \App\Enums\Order\PaymentStatus::Processing)

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-12 text-center">
        <p class="text-xs uppercase tracking-[0.25em] text-brand-signature">Commande {{ $order->number }}</p>

        @if ($cancelled)
            <h1 class="font-display text-3xl mt-3">Délai de paiement dépassé</h1>
            <p class="mt-4 text-brand-muted">
                Le paiement n'a pas été reçu à temps : la commande a été annulée et les articles remis en vente.
                Si vous avez été débitée, contactez-nous avec ce numéro de commande.
            </p>
            <div class="mt-8 flex justify-center">
                <x-shop.cta href="{{ route('shop.index') }}" variant="primary">Retour à la boutique</x-shop.cta>
            </div>
        @else
            @if ($processing && ! $errors->has('payment'))
                {{-- Rafraîchit : chaque affichage revérifie le statut auprès de PayTech. --}}
                <div x-data x-init="setTimeout(() => window.location.reload(), 5000)"></div>
                <h1 class="font-display text-3xl mt-3">Paiement en cours de vérification…</h1>
                <p class="mt-4 text-brand-muted">
                    Nous attendons la confirmation de PayTech. Cette page se met à jour toute seule.
                </p>
            @else
                <h1 class="font-display text-3xl mt-3">Le paiement n'a pas abouti</h1>
                <p class="mt-4 text-brand-muted">
                    Aucun montant n'a été encaissé. Vos articles restent réservés jusqu'à
                    {{ $payment->expires_at?->timezone(config('app.timezone'))->format('H:i') }}.
                </p>
            @endif

            @error('payment')
                <p class="mt-4 text-sm text-red-700">{{ $message }}</p>
            @enderror

            <p class="mt-6 text-sm text-brand-ink">
                Montant : <span class="font-medium">{{ number_format($order->total, 0, ',', ' ') }} FCFA</span>
            </p>

            @if ($canRetry)
                <form method="POST" action="{{ route('shop.checkout.payment.retry', $order->number) }}" class="mt-8 flex justify-center">
                    @csrf
                    <x-shop.cta type="submit" :variant="$processing ? 'secondary' : 'primary'">
                        {{ $processing ? 'Relancer le paiement' : 'Réessayer le paiement' }}
                    </x-shop.cta>
                </form>
            @endif

            <a href="{{ route('account.orders.index') }}" class="mt-4 inline-block text-sm text-brand-muted underline hover:text-brand-signature">
                Voir mes commandes
            </a>
        @endif
    </div>
</x-shop-layout>

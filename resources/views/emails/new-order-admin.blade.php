<!DOCTYPE html>
<html lang="fr">
<body style="font-family: sans-serif; color: #17151B; max-width: 560px; margin: 0 auto;">
    <h1 style="font-size: 22px;">Nouvelle commande {{ $order->number }}</h1>
    <p>
        Montant : <strong>{{ number_format($order->total, 0, ',', ' ') }} FCFA</strong>
        @if ($order->discount_total > 0)
            (dont remise {{ $order->promo_code }} : -{{ number_format($order->discount_total, 0, ',', ' ') }} FCFA)
        @endif
        ({{ $order->payment?->method ?? $order->payment?->providerLabel() }} — {{ $order->payment?->status->label() }}).<br>
        Client : {{ $order->user->name }} ({{ $order->user->email }})<br>
        Livraison : {{ $order->recipient_name }}, {{ $order->address_line }}, {{ $order->city }} — {{ $order->phone }}
    </p>
    <ul>
        @foreach ($order->items as $item)
            <li>{{ $item->product_name }} — {{ $item->quantity }} × {{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</li>
        @endforeach
    </ul>
    <p>À traiter depuis le back-office : {{ url('/admin') }}</p>
</body>
</html>

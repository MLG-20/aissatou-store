<!DOCTYPE html>
<html lang="fr">
<body style="font-family: sans-serif; color: #17151B; max-width: 560px; margin: 0 auto;">
    <h1 style="font-size: 22px;">Merci pour votre commande {{ $order->number }} !</h1>
    <p>
        Votre commande d'un montant de
        <strong>{{ number_format($order->total, 0, ',', ' ') }} FCFA</strong>
        est bien enregistrée. Vous la réglerez à la livraison, à l'adresse suivante :
    </p>
    <p>
        {{ $order->recipient_name }}<br>
        {{ $order->address_line }}, {{ $order->city }}<br>
        {{ $order->phone }}
    </p>
    <ul>
        @foreach ($order->items as $item)
            <li>
                {{ $item->product_name }}
                @if ($item->variant_label)
                    ({{ $item->variant_label }})
                @endif
                — {{ $item->quantity }} × {{ number_format($item->unit_price, 0, ',', ' ') }} FCFA
            </li>
        @endforeach
    </ul>
    <p>Vous pouvez suivre votre commande depuis votre compte, rubrique « Commandes ».</p>
    <p style="color: #8A8074; font-size: 13px;">Aissatou Store — L'élégance dans la pudeur</p>
</body>
</html>

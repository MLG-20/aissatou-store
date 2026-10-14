<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $order->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #17151B; }
        h1 { font-size: 22px; margin-bottom: 0; }
        .meta { color: #555; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #F4E6D5; }
        .total { text-align: right; font-size: 14px; margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Aissatou Store</h1>
    <p class="meta">Facture {{ $order->number }} — émise le {{ $order->created_at->format('d/m/Y') }}</p>

    <p>
        <strong>Client :</strong> {{ $order->user->name }} ({{ $order->user->email }})<br>
        <strong>Livraison :</strong> {{ $order->recipient_name }}, {{ $order->address_line }},
        {{ $order->city }} — {{ $order->phone }}<br>
        <strong>Paiement :</strong> {{ $order->payment->providerLabel() }}
    </p>

    <table>
        <thead>
            <tr><th>Article</th><th>Réf.</th><th>Qté</th><th>Prix unit.</th><th>Total</th></tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}{{ $item->variant_label ? ' ('.$item->variant_label.')' : '' }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->unit_price, 0, ',', ' ') }} FCFA</td>
                    <td>{{ number_format($item->lineTotal(), 0, ',', ' ') }} FCFA</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="total"><strong>Total : {{ number_format($order->total, 0, ',', ' ') }} FCFA</strong></p>
</body>
</html>

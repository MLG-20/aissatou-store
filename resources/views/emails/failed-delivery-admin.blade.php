<!DOCTYPE html>
<html lang="fr">
<body style="font-family: sans-serif; color: #17151B; max-width: 560px; margin: 0 auto;">
    <h1 style="font-size: 22px;">Livraison manquée : {{ $order->number }}</h1>
    <p>
        {{ $order->failed_delivery_by }} signale que la cliente était absente
        le {{ $order->failed_delivery_at->format('d/m/Y à H:i') }}{{ $order->failed_delivery_attempts > 1 ? ' ('.$order->failed_delivery_attempts.'e tentative)' : '' }}.
        Le colis reste chez le livreur et la commande reste « Expédiée ».
    </p>
    <p>
        À recontacter : <strong>{{ $order->recipient_name }}</strong> — {{ $order->phone }}<br>
        {{ $order->address_line }}, {{ $order->city }}
    </p>
    <p>
        Convenez d'un nouveau créneau avec la cliente, puis prévenez le livreur.<br>
        Fiche de la commande : {{ \App\Filament\Resources\Order\OrderResource::getUrl('view', ['record' => $order], panel: 'admin') }}
    </p>
</body>
</html>

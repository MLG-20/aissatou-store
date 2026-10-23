<!DOCTYPE html>
<html lang="fr">
<body style="font-family: sans-serif; color: #17151B; max-width: 560px; margin: 0 auto;">
    <h1 style="font-size: 22px;">
        {{ $variant->stock === 0 ? 'Rupture de stock' : 'Stock faible' }}
    </h1>
    <p>
        <strong>{{ $variant->label() }}</strong> (réf. {{ $variant->sku }})<br>
        Stock restant : <strong>{{ $variant->stock }}</strong> — seuil d'alerte : {{ $variant->lowStockThreshold() }}
    </p>
    <p>Pensez au réassort. Inventaire : {{ url('/admin') }}</p>
    <p style="color: #6B6670; font-size: 13px;">
        Vous ne recevrez pas d'autre alerte pour cette déclinaison tant que son stock ne sera pas remonté au-dessus du seuil.
    </p>
</body>
</html>

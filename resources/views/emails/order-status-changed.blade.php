<!DOCTYPE html>
<html lang="fr">
<body style="font-family: sans-serif; color: #17151B; max-width: 560px; margin: 0 auto;">
    <h1 style="font-size: 22px;">Votre commande {{ $order->number }} : {{ $status->label() }}</h1>
    <p>
        Bonne nouvelle : votre commande est désormais
        <strong>{{ mb_strtolower($status->label()) }}</strong>.
    </p>
    <p>Suivez son avancement depuis votre compte, rubrique « Commandes ».</p>
    <p style="color: #8A8074; font-size: 13px;">Aissatou Store — L'élégance dans la pudeur</p>
</body>
</html>

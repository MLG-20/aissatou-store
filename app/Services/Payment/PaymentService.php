<?php

namespace App\Services\Payment;

use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Mail\NewOrderForAdmin;
use App\Models\Order\Payment;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Seul point où un paiement électronique change d'état (phase 5) : IPN,
 * retour client, vérification admin et expiration passent tous ici.
 *
 * Idempotence : verrou sur la ligne `payments` + relecture du statut DANS la
 * transaction — un même IPN reçu trois fois (ou IPN + retour client au même
 * instant) ne confirme et n'alerte la boutique qu'une seule fois.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly OrderService $orders,
    ) {}

    /**
     * Confirme le paiement. Renvoie false s'il l'était déjà (doublon).
     *
     * @param  array<string, mixed>  $details  journalisé tel quel (sans secrets)
     */
    public function confirm(Payment $payment, array $details = [], ?string $reference = null, ?string $method = null): bool
    {
        $confirmed = DB::transaction(function () use ($payment, $details, $reference, $method): bool {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->status === PaymentStatus::Confirmed) {
                return false;
            }

            $locked->update([
                'status' => PaymentStatus::Confirmed,
                'confirmed_at' => now(),
                'reference' => $reference ?? $locked->reference,
                'method' => $method ?? $locked->method,
            ]);
            $locked->order->update(['payment_status' => PaymentStatus::Confirmed]);
            $locked->log(PaymentEventType::Confirmed, $details);

            return true;
        });

        // Après commit, une seule fois : la boutique n'est alertée que pour
        // une commande réellement payée (décision Ahmed, 2026-10-23).
        if ($confirmed) {
            $order = $payment->order->fresh(['items', 'payment', 'user']);
            Mail::to($this->orders->shopEmail())->queue(new NewOrderForAdmin($order));
        }

        return $confirmed;
    }

    /**
     * Marque l'échec (annulation chez PayTech). Un paiement confirmé n'est
     * jamais rétrogradé par ce chemin. Renvoie false si rien n'a changé.
     *
     * @param  array<string, mixed>  $details
     */
    public function fail(Payment $payment, array $details = []): bool
    {
        return DB::transaction(function () use ($payment, $details): bool {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (in_array($locked->status, [PaymentStatus::Confirmed, PaymentStatus::Refunded, PaymentStatus::Failed], strict: true)) {
                return false;
            }

            $locked->update(['status' => PaymentStatus::Failed]);
            $locked->order->update(['payment_status' => PaymentStatus::Failed]);
            $locked->log(PaymentEventType::Failed, $details);

            return true;
        });
    }

    /**
     * Vérification active auprès du fournisseur (retour client, bouton admin,
     * avant expiration) : seule voie, avec l'IPN signé, pour confirmer.
     * Renvoie le statut boutique à jour.
     */
    public function reconcile(Payment $payment, ?string $ip = null): PaymentStatus
    {
        $provider = $this->payments->find($payment->provider);
        $remote = $provider?->verify($payment);

        $payment->log(PaymentEventType::StatusChecked, ['remote' => $remote?->value ?? 'inconnu'], $ip);

        match ($remote) {
            PaymentStatus::Confirmed => $this->confirm($payment, ['source' => 'verification']),
            PaymentStatus::Failed => $this->fail($payment, ['source' => 'verification']),
            default => null,
        };

        return $payment->fresh()->status;
    }
}

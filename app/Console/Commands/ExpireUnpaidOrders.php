<?php

namespace App\Console\Commands;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Models\Order\Payment;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Console\Command;

/**
 * Annule les commandes payables en ligne restées impayées au-delà du délai
 * (`payment.expire_minutes`) et remet leur stock en vente. Planifiée toutes
 * les 5 minutes (routes/console.php).
 *
 * Avant d'annuler, on interroge le fournisseur : un paiement abouti dont
 * l'IPN se serait perdu est confirmé au lieu d'être annulé.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'payments:expire';

    protected $description = 'Annule les commandes payées en ligne restées impayées après le délai';

    public function handle(PaymentService $payments, OrderService $orders): int
    {
        $expired = Payment::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Processing, PaymentStatus::Failed])
            ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::New))
            ->with('order')
            ->get();

        $cancelled = 0;

        foreach ($expired as $payment) {
            if ($payment->status === PaymentStatus::Processing
                && $payments->reconcile($payment) === PaymentStatus::Confirmed) {
                continue;
            }

            $payments->fail($payment, ['source' => 'expiration']);
            $orders->changeStatus($payment->order, OrderStatus::Cancelled);
            $payment->fresh()->log(PaymentEventType::Expired);
            $cancelled++;
        }

        $this->info("{$cancelled} commande(s) impayée(s) annulée(s).");

        return self::SUCCESS;
    }
}

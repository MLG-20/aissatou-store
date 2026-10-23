<?php

namespace App\Http\Controllers\Payment;

use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\Payment;
use App\Models\Order\PaymentEvent;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PayTechProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Notification serveur-à-serveur de PayTech (IPN). Seule source « poussée »
 * de confirmation : la redirection du navigateur ne confirme jamais rien.
 *
 * Ordre des contrôles : authenticité → commande connue → montant identique
 * au total serveur → traitement idempotent (PaymentService). Tout rejet est
 * journalisé comme suspect, avec l'IP, pour l'audit.
 */
class PayTechIpnController extends Controller
{
    public function __construct(
        private readonly PayTechProvider $paytech,
        private readonly PaymentService $service,
    ) {}

    public function __invoke(Request $request): Response
    {
        $ipn = $request->all();
        $payment = $this->findPayment((string) ($ipn['ref_command'] ?? ''));
        $auth = $this->paytech->authenticate($ipn);

        if (! $auth) {
            return $this->reject($payment, $ipn, $request->ip(), 'signature invalide', 403);
        }

        if (! $payment) {
            return $this->reject(null, $ipn, $request->ip(), 'commande inconnue', 404);
        }

        // `item_price` = montant demandé, à comparer au total figé côté
        // serveur. (`final_item_price` peut différer : promo PayTech, ou
        // débit aléatoire 100–150 FCFA du sandbox.)
        if ((int) ($ipn['item_price'] ?? -1) !== (int) round((float) $payment->amount)) {
            return $this->reject($payment, $ipn, $request->ip(), 'montant différent', 422);
        }

        $details = $this->sanitize($ipn) + ['auth' => $auth];
        $payment->log(PaymentEventType::Notification, $details, $request->ip());

        $event = $ipn['type_event'] ?? null;

        if ($auth === 'hmac') {
            match ($event) {
                'sale_complete' => $this->service->confirm(
                    $payment, $details, (string) $ipn['ref_command'], $ipn['payment_method'] ?? null
                ),
                'sale_canceled' => $this->service->fail($payment, $details),
                default => null,
            };
        } elseif (in_array($event, ['sale_complete', 'sale_canceled'], strict: true)) {
            // Empreintes SHA-256 seules : rejouables par qui a vu un IPN, on
            // ne croit donc que la vérification active auprès de PayTech.
            $this->service->reconcile($payment, $request->ip());
        }

        return response('OK');
    }

    /** `AS-2026-X7K4PQ-2` → paiement PayTech de la commande AS-2026-X7K4PQ. */
    private function findPayment(string $reference): ?Payment
    {
        if (! preg_match('/^(AS-\d{4}-[A-Z0-9]{6})-\d+$/', $reference, $matches)) {
            return null;
        }

        return Order::where('number', $matches[1])->first()?->payment()->where('provider', 'paytech')->first();
    }

    private function reject(?Payment $payment, array $ipn, ?string $ip, string $reason, int $status): Response
    {
        PaymentEvent::create([
            'payment_id' => $payment?->id,
            'provider' => 'paytech',
            'type' => PaymentEventType::SuspiciousNotification,
            'status' => $payment?->status->value ?? PaymentStatus::Pending->value,
            'payload' => $this->sanitize($ipn) + ['motif' => $reason],
            'ip' => $ip,
        ]);

        return response('Refusé', $status);
    }

    /**
     * Ce qui est utile à un audit, sans empreintes de clés ni numéro complet
     * du payeur (la boutique ne conserve que référence et statut).
     *
     * @return array<string, mixed>
     */
    private function sanitize(array $ipn): array
    {
        $kept = array_intersect_key($ipn, array_flip([
            'type_event', 'ref_command', 'item_price', 'final_item_price', 'payment_method', 'token', 'env',
        ]));

        if (filled($ipn['client_phone'] ?? null)) {
            $kept['client_phone'] = '…'.substr((string) $ipn['client_phone'], -2);
        }

        return $kept;
    }
}

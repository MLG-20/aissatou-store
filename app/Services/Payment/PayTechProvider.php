<?php

namespace App\Services\Payment;

use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Models\Order\Order;
use App\Models\Order\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * PayTech (paytech.sn) : un agrégateur pour Wave, Orange Money, Free Money et
 * carte. Inactif tant que `PAYTECH_API_KEY`/`PAYTECH_API_SECRET` sont vides.
 *
 * Garanties (phase 5, sécurité) :
 * - le montant envoyé vient de `payments.amount`, figé par OrderService à
 *   partir du calcul serveur — jamais d'une valeur du client ;
 * - timeout strict + nouvelle tentative sur erreur RÉSEAU uniquement (jamais
 *   sur un refus de PayTech) : le client n'est jamais bloqué indéfiniment ;
 * - le journal ne contient jamais les clés, seulement référence/jeton/montant.
 */
class PayTechProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'paytech';
    }

    public function label(): string
    {
        return 'Wave, Orange Money ou carte';
    }

    public function isAvailable(): bool
    {
        return filled(config('payment.paytech.api_key'))
            && filled(config('payment.paytech.api_secret'));
    }

    public function initiate(Order $order): PaymentStatus
    {
        $order->payment()->create([
            'provider' => $this->key(),
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'expires_at' => now()->addMinutes(config('payment.expire_minutes')),
        ]);

        return PaymentStatus::Pending;
    }

    public function checkout(Payment $payment): ?string
    {
        $order = $payment->order;

        // PayTech refuse une `ref_command` déjà utilisée (409) : chaque
        // tentative a son suffixe, la commande se retrouve en retirant -N.
        $attempt = $payment->events()->where('type', PaymentEventType::Initiated)->count() + 1;
        $reference = $order->number.'-'.$attempt;

        $payload = [
            'item_name' => 'Commande '.$order->number,
            'item_price' => (int) round((float) $payment->amount),
            'currency' => 'XOF',
            'ref_command' => $reference,
            'command_name' => 'Aissatou Store — commande '.$order->number,
            'env' => config('payment.paytech.env'),
            'ipn_url' => config('payment.paytech.ipn_url') ?: route('payment.paytech.ipn'),
            'success_url' => route('shop.checkout.payment', $order->number),
            'cancel_url' => route('shop.checkout.payment', $order->number),
        ];

        try {
            $response = $this->client()->post('/payment/request-payment', $payload);
        } catch (ConnectionException) {
            $payment->log(PaymentEventType::InitiationFailed, ['ref_command' => $reference, 'error' => 'injoignable']);

            throw PaymentException::unreachable();
        }

        $token = $response->json('token');
        $url = $response->json('redirect_url');

        // On redirige le client vers cette URL : uniquement le domaine PayTech
        // en HTTPS (défense en profondeur contre une réponse altérée).
        $trusted = is_string($url) && str_starts_with($url, 'https://paytech.sn/');

        if (! $response->successful() || (int) $response->json('success') !== 1 || ! $token || ! $trusted) {
            $payment->log(PaymentEventType::InitiationFailed, [
                'ref_command' => $reference,
                'http' => $response->status(),
                'message' => $response->json('message'),
            ]);

            throw PaymentException::rejected();
        }

        $payment->update(['token' => $token, 'status' => PaymentStatus::Processing]);
        $payment->order->update(['payment_status' => PaymentStatus::Processing]);
        $payment->log(PaymentEventType::Initiated, [
            'ref_command' => $reference,
            'token' => $token,
            'amount' => $payload['item_price'],
            'env' => $payload['env'],
        ]);

        return $url;
    }

    public function isOnline(): bool
    {
        return true;
    }

    public function verify(Payment $payment): ?PaymentStatus
    {
        $state = $payment->token ? ($this->fetchStatus($payment->token)['state'] ?? null) : null;

        return $state ? self::statusFromState((string) $state) : null;
    }

    /**
     * `state` de `get-status` → statut boutique. Observés en sandbox
     * (2026-10-23) : `running` (en attente) et `success` (payé, Orange
     * Money). Un état inconnu renvoie null : on ne conclut jamais sur ce
     * qu'on ne connaît pas.
     */
    public static function statusFromState(string $state): ?PaymentStatus
    {
        return match (strtolower($state)) {
            'running', 'pending' => PaymentStatus::Processing,
            'completed', 'complete', 'success', 'paid', 'sale_complete' => PaymentStatus::Confirmed,
            'canceled', 'cancelled', 'failed', 'expired', 'sale_canceled' => PaymentStatus::Failed,
            default => null,
        };
    }

    /**
     * Authenticité d'un IPN. HMAC-SHA256 (clé = secret) recommandé par
     * PayTech sur `montant|ref_command|api_key` ; la doc hésite entre
     * `final_item_price` et `item_price`, les deux sont acceptés (seul le
     * détenteur du secret peut produire l'un ou l'autre). À défaut de HMAC :
     * empreintes SHA-256 des clés — plus faibles, donc l'appelant doit en
     * plus confirmer par `verify()`.
     *
     * @return 'hmac'|'sha256'|null
     */
    public function authenticate(array $ipn): ?string
    {
        $key = (string) config('payment.paytech.api_key');
        $secret = (string) config('payment.paytech.api_secret');

        if (filled($ipn['hmac_compute'] ?? null)) {
            foreach (array_unique(array_filter([$ipn['final_item_price'] ?? null, $ipn['item_price'] ?? null])) as $amount) {
                $expected = hash_hmac('sha256', $amount.'|'.($ipn['ref_command'] ?? '').'|'.$key, $secret);

                if (hash_equals($expected, (string) $ipn['hmac_compute'])) {
                    return 'hmac';
                }
            }

            return null;
        }

        $keyOk = hash_equals(hash('sha256', $key), (string) ($ipn['api_key_sha256'] ?? ''));
        $secretOk = hash_equals(hash('sha256', $secret), (string) ($ipn['api_secret_sha256'] ?? ''));

        return $keyOk && $secretOk ? 'sha256' : null;
    }

    /**
     * État brut de la session chez PayTech (`running`, …), ou null si
     * injoignable / jeton inconnu.
     *
     * @return array<string, mixed>|null
     */
    public function fetchStatus(string $token): ?array
    {
        try {
            $response = $this->client()->get('/payment/get-status', ['token_payment' => $token]);
        } catch (ConnectionException) {
            return null;
        }

        return $this->paymentFrom($response);
    }

    /** @return array<string, mixed>|null */
    private function paymentFrom(Response $response): ?array
    {
        if (! $response->successful() || (int) $response->json('success') !== 1) {
            return null;
        }

        $payment = $response->json('payment');

        return is_array($payment) ? $payment : null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('payment.paytech.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(config('payment.paytech.timeout'))
            ->connectTimeout(5)
            ->retry(2, 300, fn (\Throwable $e) => $e instanceof ConnectionException, throw: false)
            ->withHeaders([
                'API_KEY' => config('payment.paytech.api_key'),
                'API_SECRET' => config('payment.paytech.api_secret'),
            ]);
    }
}

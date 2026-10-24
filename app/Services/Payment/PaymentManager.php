<?php

namespace App\Services\Payment;

/**
 * Registre des moyens de paiement actifs. Le tunnel ne demande que
 * `available()` : l'ajout d'un fournisseur en phase 5 se fait ici, le
 * contrôleur n'a pas à changer.
 */
class PaymentManager
{
    /** @return list<PaymentProvider> */
    public function available(): array
    {
        return collect($this->providers())
            ->filter(fn (PaymentProvider $provider) => $provider->isAvailable())
            ->values()
            ->all();
    }

    public function find(string $key): ?PaymentProvider
    {
        return collect($this->providers())->firstWhere(fn (PaymentProvider $provider) => $provider->key() === $key);
    }

    /**
     * Clés des fournisseurs en ligne, même désactivés : les paiements passés
     * restent consultables au back-office.
     *
     * @return list<string>
     */
    public function onlineKeys(): array
    {
        return collect($this->providers())
            ->filter(fn (PaymentProvider $provider) => $provider->isOnline())
            ->map(fn (PaymentProvider $provider) => $provider->key())
            ->values()
            ->all();
    }

    /** @return list<PaymentProvider> */
    private function providers(): array
    {
        return [
            app(CodProvider::class),
            app(PayTechProvider::class),
        ];
    }
}

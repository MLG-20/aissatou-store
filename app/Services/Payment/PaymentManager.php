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

    /** @return list<PaymentProvider> */
    private function providers(): array
    {
        return [
            app(CodProvider::class),
            app(PayTechProvider::class),
        ];
    }
}

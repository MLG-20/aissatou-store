<?php

namespace App\Services\Delivery;

use Illuminate\Support\Facades\Http;

/**
 * Yango Delivery (Dakar) : inactif tant que la cliente n'a pas son compte
 * business et ses clés (`YANGO_*` dans `.env` — jamais commitées).
 *
 * Exigences d'intégration respectées dès l'écriture : timeout strict (pas de
 * tunnel bloqué si Yango est lent), repli manuel en cas d'indisponibilité,
 * secrets uniquement en variables d'environnement (cahier des charges,
 * section 4, sécurité).
 */
class YangoDeliveryProvider implements DeliveryProvider
{
    public function key(): string
    {
        return 'yango';
    }

    public function label(): string
    {
        return 'Yango Delivery';
    }

    public function isAvailable(): bool
    {
        return filled(config('delivery.yango.key'))
            && filled(config('delivery.yango.client_id'));
    }

    public function quote(?string $city): ?float
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            // Devis indicatif : le chiffrage contractuel exact (coordonnées
            // GPS, type de course) sera branché avec les accès de la cliente.
            $response = Http::timeout(5)->get(
                rtrim((string) config('delivery.yango.base_url'), '/').'/quote',
                [
                    'clid' => config('delivery.yango.client_id'),
                    'city' => $city,
                ]
            );

            if (! $response->successful()) {
                return null;
            }

            $fee = $response->json('fee');

            return is_numeric($fee) ? (float) $fee : null;
        } catch (\Throwable) {
            // Transporteur injoignable : null, pas d'exception — le tunnel
            // bascule sur le manuel au lieu de bloquer le client.
            return null;
        }
    }
}

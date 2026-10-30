<?php

namespace Tests\Concerns;

/**
 * Pour les tests qui commandent en paiement à la livraison, coupé par défaut
 * depuis Réglages du site. Appelé par Laravel après la remise à zéro de la base.
 */
trait WithCashOnDelivery
{
    protected function setUpWithCashOnDelivery(): void
    {
        $this->enableCashOnDelivery();
    }
}

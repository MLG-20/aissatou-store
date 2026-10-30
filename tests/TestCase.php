<?php

namespace Tests;

use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Paiement à la livraison : coupé par défaut, rallumé pour les tests qui commandent avec. */
    protected function enableCashOnDelivery(): void
    {
        SiteSetting::current()->update(['cash_on_delivery_enabled' => true]);
    }
}

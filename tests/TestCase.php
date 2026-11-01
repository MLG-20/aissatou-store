<?php

namespace Tests;

use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sinon chaque test du back-office devrait d'abord passer le code : AdminTwoFactorTest le remet.
        $this->withoutMiddleware(EnsureAdminTwoFactor::class);
    }

    /** Paiement à la livraison : coupé par défaut, rallumé pour les tests qui commandent avec. */
    protected function enableCashOnDelivery(): void
    {
        SiteSetting::current()->update(['cash_on_delivery_enabled' => true]);
    }
}

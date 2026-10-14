<?php

namespace Tests\Feature\Delivery;

use App\Models\Delivery\DeliveryZone;
use App\Services\Delivery\DeliveryManager;
use App\Services\Delivery\ManualDeliveryProvider;
use App\Services\Delivery\YangoDeliveryProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_is_always_available_and_quotes_zones(): void
    {
        DeliveryZone::create(['name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1000, 'is_active' => true]);

        $manager = app(DeliveryManager::class);

        $this->assertInstanceOf(ManualDeliveryProvider::class, $manager->primary());
        $this->assertEquals(1000, $manager->primary()->quote('Dakar'));
    }

    public function test_yango_is_inactive_without_credentials(): void
    {
        config(['delivery.yango.key' => null, 'delivery.yango.client_id' => null]);

        $this->assertFalse(app(YangoDeliveryProvider::class)->isAvailable());
        $this->assertNull(app(YangoDeliveryProvider::class)->quote('Dakar'));
    }

    public function test_yango_failure_falls_back_instead_of_blocking(): void
    {
        DeliveryZone::create(['name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1000, 'is_active' => true]);
        config(['delivery.yango.key' => 'test', 'delivery.yango.client_id' => 'test']);
        Http::fake(fn () => Http::response([], 500));

        // Configuré donc sélectionné, mais injoignable : le devis rend null
        // sans lever — l'appelant bascule sur le manuel au lieu de bloquer.
        $this->assertInstanceOf(YangoDeliveryProvider::class, app(DeliveryManager::class)->primary());
        $this->assertNull(app(DeliveryManager::class)->primary()->quote('Dakar'));
        $this->assertEquals(1000, app(ManualDeliveryProvider::class)->quote('Dakar'));
    }
}

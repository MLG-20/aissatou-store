<?php

namespace Tests\Feature\Delivery;

use App\Filament\Resources\Delivery\DeliveryZoneResource\Pages\CreateDeliveryZone;
use App\Models\Admin;
use App\Models\Delivery\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_city_matches_its_zone_case_and_accent_insensitively(): void
    {
        DeliveryZone::create([
            'name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1500, 'is_active' => true,
        ]);

        $this->assertEquals(1500, (float) DeliveryZone::forCity('dakar')->fee);
        $this->assertEquals(1500, (float) DeliveryZone::forCity('  DAKAR  ')->fee);
    }

    public function test_an_unknown_city_falls_back_to_the_default_zone(): void
    {
        DeliveryZone::create([
            'name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1500, 'is_active' => true,
        ]);
        DeliveryZone::create([
            'name' => 'Autres zones', 'city' => null, 'fee' => 3000, 'is_active' => true,
        ]);

        $this->assertEquals(3000, (float) DeliveryZone::forCity('Thiès')->fee);
    }

    public function test_an_inactive_zone_is_never_used(): void
    {
        DeliveryZone::create([
            'name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1500, 'is_active' => false,
        ]);
        DeliveryZone::create([
            'name' => 'Autres zones', 'city' => null, 'fee' => 3000, 'is_active' => true,
        ]);

        $this->assertEquals(3000, (float) DeliveryZone::forCity('Dakar')->fee);
    }

    public function test_without_any_zone_the_fee_is_zero_and_nothing_breaks(): void
    {
        $this->assertNull(DeliveryZone::forCity('Dakar'));
    }

    public function test_updating_a_zone_invalidates_the_cache(): void
    {
        DeliveryZone::create(['name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1000, 'is_active' => true]);
        $this->assertEquals(1000, (float) DeliveryZone::forCity('Dakar')->fee);

        DeliveryZone::where('city', 'Dakar')->first()->update(['fee' => 2500]);

        $this->assertEquals(2500, (float) DeliveryZone::forCity('Dakar')->fee);
    }

    public function test_admin_can_create_a_zone_from_the_panel(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm([
                'name' => 'Thiès',
                'city' => 'Thies',
                'fee' => 2000,
                'delay_label' => '2-3 jours',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals(2000, (float) DeliveryZone::forCity('thiès')->fee);
    }
}

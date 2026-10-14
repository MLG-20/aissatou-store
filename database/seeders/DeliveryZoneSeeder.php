<?php

namespace Database\Seeders;

use App\Models\Delivery\DeliveryZone;
use Illuminate\Database\Seeder;

/**
 * Zones de démo (Phase 4) : tarifs indicatifs Dakar et environs, à ajuster
 * avec la cliente (zones réelles, transporteurs, délais). La zone par défaut
 * couvre toute ville non listée pour que le tunnel ne bloque jamais.
 */
class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1000, 'delay_label' => '24-48h', 'position' => 1],
            ['name' => 'Pikine – Guédiawaye', 'city' => 'Pikine', 'fee' => 1500, 'delay_label' => '24-48h', 'position' => 2],
            ['name' => 'Rufisque', 'city' => 'Rufisque', 'fee' => 2000, 'delay_label' => '2-3 jours', 'position' => 3],
            ['name' => 'Autres zones', 'city' => null, 'fee' => 3500, 'delay_label' => '3-5 jours', 'position' => 99],
        ];

        foreach ($zones as $zone) {
            DeliveryZone::updateOrCreate(
                ['city' => $zone['city']],
                $zone + ['is_active' => true],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Catalogue\Material;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Valeurs du filtre « Matière » (Phase 2.2A) : libellés de la maquette p.12.
 * La cliente les gère ensuite depuis Catalogue > Matières.
 */
class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Soie', 'Modal', 'Coton', 'Laine & cachemire'] as $position => $name) {
            Material::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'position' => $position + 1, 'is_active' => true],
            );
        }
    }
}

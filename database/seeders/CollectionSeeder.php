<?php

namespace Database\Seeders;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Les quatre collections de la maquette p.12 (Phase 2.2B), avec des
 * rattachements de démonstration — la cliente les réorganise depuis
 * Catalogue > Collections.
 */
class CollectionSeeder extends Seeder
{
    public function run(): void
    {
        $collections = [
            'Atelier Nocturne' => ['Les pièces sombres et profondes de la maison.', ['Encre', 'Cassis', 'Nuit'], 'Foulards'],
            'Essentiels' => ['Les indispensables du quotidien, à porter sans hésiter.', ['Ivoire', 'Jersey', 'Coton', 'Quotidien'], 'Hijabs'],
            'Saison' => ['La sélection du moment, entre chaleur et légèreté.', ['Hiver', 'Cachemire', 'Sauge'], 'Cols'],
            "L'art d'offrir" => ['Parfums, coffrets et attentions prêts à offrir.', ['Parfum', 'Musc', 'Ambre', 'Coffret', 'Broche', 'Cadeau', 'Écrin', 'Carte'], 'Cadeaux'],
        ];

        foreach ($collections as $name => [$description, $keywords, $categoryName]) {
            $collection = Collection::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'image_path' => Category::where('name', $categoryName)->first()?->image_path,
                    'is_active' => true,
                ],
            );

            $ids = Product::query()
                ->where(function ($query) use ($keywords): void {
                    foreach ($keywords as $keyword) {
                        $query->orWhere('name', 'like', "%{$keyword}%");
                    }
                })
                ->pluck('id');

            $collection->products()->syncWithoutDetaching($ids);
        }
    }
}

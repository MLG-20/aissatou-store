<?php

namespace Database\Seeders;

use App\Models\Catalogue\Product;
use App\Models\Content\Look;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Look de démonstration pour la section « Le Look » de l'accueil, en attendant
 * une vraie photo de mise en situation fournie par la cliente. Il reprend la
 * photo du hero au hijab vert, COPIÉE dans `looks/` : supprimer le look ne
 * doit pas effacer les variantes de l'image du hero.
 *
 * Idempotent : ne fait rien si un look existe déjà ou si la photo manque.
 */
class LookDemoSeeder extends Seeder
{
    private const SOURCE = 'hero/01KX4EMZ0366WGYP4V059YVJ5D.webp';

    private const TARGET = 'looks/demo-look-hijab-vert.webp';

    public function run(): void
    {
        $disk = Storage::disk('public');

        if (Look::query()->exists() || ! $disk->exists(self::SOURCE)) {
            return;
        }

        $disk->copy(self::SOURCE, self::TARGET);

        $look = Look::create([
            'title' => 'Le voile du quotidien',
            'subtitle' => 'Une mousseline légère qui se drape sans effort, un col brodé qui signe la blouse.',
            'image_path' => self::TARGET,
            'position' => 1,
            'is_active' => true,
        ]);

        // Positions en % de la photo, relevées sur l'image : le voile sur la
        // tête, le pan drapé au premier plan, le col de la blouse.
        $spots = [
            ['Voile Mousseline Bordeaux', 71, 13],
            ['Voile Soie Naturelle Beige', 22, 58],
            ['Col Claudine Brodé', 88, 41],
        ];

        foreach ($spots as $index => [$name, $x, $y]) {
            $product = Product::query()->where('name', $name)->first();

            if ($product) {
                $look->hotspots()->create(['product_id' => $product->id, 'x' => $x, 'y' => $y, 'position' => $index]);
            }
        }
    }
}

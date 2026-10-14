<?php

namespace Database\Seeders;

use App\Enums\Catalogue\ProductLabel;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Peuple Cols et Cadeaux, restées vides après le renommage des catégories :
 * aucun produit de l'ancienne marque ne leur correspondait, si bien qu'elles
 * s'affichaient sans article sur le site.
 *
 * Produits de DÉMONSTRATION, sans photo : ils montrent le visuel « signature »
 * par défaut (x-shop.image-placeholder), et la cliente remplace image, contenu
 * et prix depuis l'admin. La référence article et la référence de déclinaison
 * sont générées automatiquement par les modèles (rien à saisir ici).
 *
 * Idempotent : le rattrapage se fait par slug, on peut le rejouer sans créer de
 * doublon.
 *
 *     php artisan db:seed --class=ColsCadeauxDemoSeeder
 */
class ColsCadeauxDemoSeeder extends Seeder
{
    /**
     * @var array<string, array<int, array{name: string, material_tagline: string, price: int, label: ?ProductLabel, description: string, variants: array<int, array{couleur: string}>}>>
     */
    private const CATALOGUE = [
        'cols' => [
            [
                'name' => 'Col Claudine Brodé',
                'material_tagline' => 'Coton piqué — bord festonné',
                'price' => 12000,
                'label' => null,
                'description' => "Un col Claudine amovible qui structure une tenue en un geste. Se pose sur une robe, un pull fin ou une chemise pour rehausser l'encolure.",
                'variants' => [['couleur' => 'Ivoire'], ['couleur' => 'Noir']],
            ],
            [
                'name' => 'Col Cravate Satin',
                'material_tagline' => 'Satin de soie — tombé souple',
                'price' => 15000,
                'label' => ProductLabel::Selected,
                'description' => 'Un col cravate qui se noue librement, entre le lavallière et le foulard. La touche qui achève une silhouette sobre.',
                'variants' => [['couleur' => 'Cassis'], ['couleur' => 'Sauge']],
            ],
            [
                'name' => 'Col Perlé Cérémonie',
                'material_tagline' => 'Tulle brodé — perles cousues main',
                'price' => 22000,
                'label' => ProductLabel::LimitedEdition,
                'description' => 'Un col de cérémonie entièrement perlé, pensé pour les grandes occasions. Chaque perle est cousue à la main.',
                'variants' => [['couleur' => 'Ivoire'], ['couleur' => 'Doré']],
            ],
        ],
        'cadeaux' => [
            [
                'name' => 'Coffret Découverte Parfum',
                'material_tagline' => 'Trois eaux de parfum — 15 ml chacune',
                'price' => 28000,
                'label' => ProductLabel::Selected,
                'description' => "Un coffret de trois fragrances à découvrir ou à offrir, présenté dans un écrin sobre. L'entrée idéale dans l'univers olfactif de la maison.",
                'variants' => [['couleur' => 'Coffret Encre']],
            ],
            [
                'name' => 'Écrin Foulard & Broche',
                'material_tagline' => 'Foulard soie & broche assortie',
                'price' => 35000,
                'label' => null,
                'description' => 'Un foulard de soie et sa broche, réunis dans un écrin prêt à offrir. Le duo se porte ensemble ou séparément.',
                'variants' => [['couleur' => 'Cassis'], ['couleur' => 'Sauge']],
            ],
            [
                'name' => 'Carte Cadeau Aissatou',
                'material_tagline' => 'Montant libre — validité un an',
                'price' => 25000,
                'label' => null,
                'description' => 'Une carte cadeau à offrir, valable un an sur toute la sélection. Le choix revient à celle qui la reçoit.',
                'variants' => [['couleur' => 'Parchemin']],
            ],
            [
                'name' => "Coffret L'Art d'Offrir",
                'material_tagline' => 'Emballage soigné — mot personnalisé',
                'price' => 18000,
                'label' => ProductLabel::New,
                'description' => "Un service d'emballage cadeau : votre sélection présentée avec soin, accompagnée d'un mot personnalisé de votre part.",
                'variants' => [['couleur' => 'Encre'], ['couleur' => 'Parchemin']],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOGUE as $categorySlug => $products) {
            $category = Category::where('slug', $categorySlug)->first();

            if (! $category) {
                $this->command?->warn("Catégorie « {$categorySlug} » absente — exécuter d'abord CategoryTreeSeeder.");

                continue;
            }

            foreach ($products as $attributes) {
                $product = Product::withTrashed()->firstOrNew([
                    'slug' => Str::slug($attributes['name']),
                ]);

                // Produit déjà présent (personnalisé en admin, ou seeder rejoué) :
                // on n'écrase rien.
                if ($product->exists) {
                    $this->command?->info("Déjà présent : {$attributes['name']}");

                    continue;
                }

                $product->fill([
                    'category_id' => $category->id,
                    'name' => $attributes['name'],
                    'description' => $attributes['description'],
                    'material_tagline' => $attributes['material_tagline'],
                    'price' => $attributes['price'],
                    'label' => $attributes['label'],
                    'is_active' => true,
                ])->save();

                foreach ($attributes['variants'] as $variant) {
                    // Stock de démonstration ; référence de déclinaison générée
                    // automatiquement par le modèle.
                    $product->variants()->create([
                        'attributes' => $variant,
                        'stock' => 10,
                        'is_active' => true,
                    ]);
                }

                $this->command?->info("Créé : {$product->name} ({$product->sku})");
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\Catalogue\ProductLabel;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Material;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogueSeeder extends Seeder
{
    /**
     * Données de démonstration alignées sur la maquette Aissatou Store
     * (rapport d'identité p.3 pour l'arborescence, p.11-12 pour les produits).
     *
     * L'arborescence est volontairement plate : le rapport présente les cinq
     * univers au même niveau, sans sous-catégories. Les axes transverses
     * (matière, collection) arriveront en Phase 2.2 sous forme de filtres et
     * non de catégories — c'est ce qui permet à un même produit d'appartenir à
     * « Soie » et à « Atelier Nocturne » sans dupliquer l'arbre.
     *
     * Les vraies fiches (photos, textes et prix définitifs) restent à saisir
     * par la cliente : ceci n'est qu'un jeu de démonstration cohérent.
     */
    public function run(): void
    {
        $hijabs = Category::factory()->create([
            'name' => 'Hijabs',
            'slug' => 'hijabs',
            'description' => 'Le geste quotidien : des hijabs choisis pour leur tombé et leur confort.',
            'position' => 1,
        ]);

        $foulards = Category::factory()->create([
            'name' => 'Foulards',
            'slug' => 'foulards',
            'description' => 'La matière et le mouvement, en soie, en modal ou en laine.',
            'position' => 2,
        ]);

        $cols = Category::factory()->create([
            'name' => 'Cols',
            'slug' => 'cols',
            'description' => 'La structure de silhouette : des cols qui achèvent une tenue.',
            'position' => 3,
        ]);

        $parfums = Category::factory()->create([
            'name' => 'Parfums',
            'slug' => 'parfums',
            'description' => 'La trace olfactive, entre tradition et modernité.',
            'position' => 4,
        ]);

        $cadeaux = Category::factory()->create([
            'name' => 'Cadeaux',
            'slug' => 'cadeaux',
            'description' => "L'art d'offrir : des objets choisis, présentés avec soin.",
            'position' => 5,
        ]);

        // Les noms proviennent directement des maquettes p.11-12. Les prix sont
        // ceux des maquettes convertis à ~656 FCFA/EUR — à faire confirmer par
        // la cliente, qui seule fixe sa grille tarifaire.
        $this->seedProducts($hijabs, [
            ['name' => 'Hijab Soie Sable', 'description' => 'Soie naturelle au tombé fluide et à la brillance discrète, pour les grandes occasions.', 'price' => 44000, 'label' => ProductLabel::Selected, 'material' => 'Soie'],
            ['name' => 'Hijab Modal Cassis', 'description' => 'Modal souple, tombé fluide et maintien fiable tout au long de la journée.', 'price' => 27500, 'material' => 'Modal'],
            ['name' => 'Hijab Jersey Ivoire', 'description' => 'Jersey extensible, doux et facile à draper, le compagnon du quotidien.', 'price' => 16000, 'material' => 'Coton'],
        ], 'couleur');

        $this->seedProducts($foulards, [
            ['name' => 'Foulard Atelier Nocturne', 'description' => "Pièce d'édition en soie, motif dérivé du pli textile de la maison.", 'price' => 62000, 'label' => ProductLabel::LimitedEdition, 'material' => 'Soie'],
            ['name' => 'Foulard Sauge Fumée', 'description' => 'Un vert doux et minéral, en modal léger, qui accompagne sans surcharger.', 'price' => 49000, 'material' => 'Modal'],
            ['name' => "Étole d'Hiver Encre", 'description' => 'Laine et cachemire, largeur généreuse et chaleur immédiate.', 'price' => 72000, 'label' => ProductLabel::New, 'material' => 'Laine & cachemire'],
        ], 'couleur');

        $this->seedProducts($cols, [
            ['name' => 'Col Cachemire Parchemin', 'description' => "Col court en cachemire, pensé pour structurer une silhouette sans l'alourdir.", 'price' => 38000, 'material' => 'Laine & cachemire'],
            ['name' => 'Col Coton Encre', 'description' => 'Coton dense et mat, une base sobre qui se porte toute l\'année.', 'price' => 21000, 'material' => 'Coton'],
        ], 'couleur');

        $this->seedProducts($parfums, [
            ['name' => 'Brume Textile Cassis', 'description' => 'Brume à vaporiser sur le tissu, sillage fruité et boisé qui tient sur la matière.', 'price' => 31500],
            ['name' => 'Eau de Parfum Ambre Doré', 'description' => "Notes chaudes d'ambre et de vanille, un sillage enveloppant qui persiste.", 'price' => 25000],
            ['name' => 'Musc Blanc Intense', 'description' => 'Un musc blanc pur et délicat, pour une signature olfactive discrète.', 'price' => 18000],
        ], 'contenance');

        $this->seedProducts($cadeaux, [
            ['name' => 'Broche Rouge Garance', 'description' => "Broche émaillée reprenant l'incision de sélection du signe de la maison.", 'price' => 19000, 'label' => ProductLabel::Selected],
            ['name' => 'Coffret Foulard Signature', 'description' => 'Un foulard présenté dans le coffret cassis de la maison, prêt à offrir.', 'price' => 68000],
        ], 'couleur');
    }

    /**
     * @param  array<int, array{name: string, description: string, price: int, label?: ProductLabel, material?: string}>  $products
     */
    private function seedProducts(Category $category, array $products, string $variantType): void
    {
        foreach ($products as $data) {
            $product = Product::factory()->for($category)->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'],
                'price' => $data['price'],
                'label' => $data['label'] ?? null,
                'material_id' => isset($data['material'])
                    ? Material::query()->where('name', $data['material'])->first()?->id
                    : null,
            ]);

            // Les déclinaisons suivent la nature du produit : une contenance pour
            // un parfum, une couleur pour du textile.
            $variants = match ($variantType) {
                'contenance' => array_map(
                    fn (string $contenance) => ['contenance' => $contenance],
                    ['30 ml', '50 ml', '100 ml']
                ),
                default => array_map(
                    fn (string $couleur) => ['couleur' => $couleur],
                    ['Parchemin', 'Cassis', 'Encre']
                ),
            };

            foreach (array_slice($variants, 0, 2) as $attributes) {
                ProductVariant::factory()->for($product)->create(['attributes' => $attributes]);
            }
        }
    }
}

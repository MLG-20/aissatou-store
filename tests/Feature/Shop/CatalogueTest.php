<?php

namespace Tests\Feature\Shop;

use App\Enums\Catalogue\ProductLabel;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_lists_active_products_only(): void
    {
        $visible = Product::factory()->create(['name' => 'Voile visible', 'is_active' => true, 'label' => ProductLabel::New]);
        $hidden = Product::factory()->create(['name' => 'Voile caché', 'is_active' => false, 'label' => ProductLabel::New]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee($visible->name);
        $response->assertDontSee($hidden->name);
    }

    public function test_the_homepage_only_shows_products_marked_as_new(): void
    {
        $marked = Product::factory()->create(['name' => 'Hijab annoncé', 'is_active' => true, 'label' => ProductLabel::New]);
        // Créé APRÈS le précédent : sous l'ancienne règle (« les dernières
        // lignes créées »), c'est lui qui serait apparu en nouveauté.
        $recent = Product::factory()->create(['name' => 'Sac récent', 'is_active' => true, 'label' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee($marked->name);
        $response->assertDontSee($recent->name);
    }

    public function test_the_homepage_hides_the_new_arrivals_section_when_nothing_is_marked(): void
    {
        Product::factory()->count(3)->create(['is_active' => true, 'label' => null]);

        $response = $this->get('/');

        $response->assertOk();
        // Mieux vaut ne rien annoncer que d'annoncer de fausses nouveautés.
        $response->assertDontSee('À découvrir');
    }

    public function test_the_catalogue_filters_on_new_arrivals(): void
    {
        $marked = Product::factory()->create(['name' => 'Foulard marqué', 'is_active' => true, 'label' => ProductLabel::New]);
        $other = Product::factory()->create(['name' => 'Foulard courant', 'is_active' => true, 'label' => null]);
        $selected = Product::factory()->create(['name' => 'Foulard sélectionné', 'is_active' => true, 'label' => ProductLabel::Selected]);

        $response = $this->get('/catalogue?nouveautes=1');

        $response->assertOk();
        $response->assertSee($marked->name);
        $response->assertDontSee($other->name);
        // Un autre signal commercial n'est pas une nouveauté.
        $response->assertDontSee($selected->name);
    }

    public function test_the_catalogue_announces_which_selection_it_shows(): void
    {
        Product::factory()->create(['is_active' => true, 'label' => ProductLabel::New]);

        $this->get('/catalogue?nouveautes=1')->assertSee('Nouveautés')->assertDontSee('Tout le catalogue');
        $this->get('/catalogue')->assertSee('Tout le catalogue');
    }

    public function test_the_catalogue_page_paginates_products(): void
    {
        Product::factory()->count(15)->create(['is_active' => true]);

        $response = $this->get('/catalogue');

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->count() === 12 && $products->total() === 15);
    }

    public function test_an_inactive_product_returns_a_404(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->get("/produits/{$product->slug}")->assertNotFound();
    }

    public function test_a_soft_deleted_product_returns_a_404(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->get("/produits/{$product->slug}")->assertNotFound();
    }

    public function test_the_product_page_displays_active_variants_with_their_price(): void
    {
        $product = Product::factory()->create(['price' => 10000]);
        $variant = ProductVariant::factory()->for($product)->create([
            'attributes' => ['taille' => 'M', 'couleur' => 'Noir'],
            'price_override' => null,
            'stock' => 5,
            'is_active' => true,
        ]);
        $inactiveVariant = ProductVariant::factory()->for($product)->create(['is_active' => false]);

        $response = $this->get("/produits/{$product->slug}");

        $response->assertOk();
        $response->assertSee('Taille : M');
        $response->assertDontSee($inactiveVariant->sku);
    }

    public function test_the_product_page_shows_the_five_levels_of_the_product_hierarchy(): void
    {
        // Les cinq niveaux de la p.3 du rapport d'identité : catégorie, nom,
        // matière/bénéfice, prix, signal. Trois manquaient sur la fiche.
        $category = Category::factory()->create(['name' => 'Hijabs', 'is_active' => true]);
        $product = Product::factory()->for($category)->create([
            'name' => 'Hijab Modal Cassis',
            'material_tagline' => 'Modal souple — tombé fluide',
            'price' => 42000,
            'label' => ProductLabel::Selected,
            'is_active' => true,
        ]);

        $response = $this->get("/produits/{$product->slug}");

        $response->assertOk();
        $response->assertSee('Hijabs');
        $response->assertSee($product->name);
        $response->assertSee($product->material_tagline);
        $response->assertSee('42 000 FCFA');
        $response->assertSee('Sélectionné');
    }

    public function test_the_product_card_shows_the_material(): void
    {
        $product = Product::factory()->create([
            'material_tagline' => 'Soie lavée — reflet mat',
            'is_active' => true,
            'label' => ProductLabel::New,
        ]);

        $this->get('/catalogue')->assertOk()->assertSee($product->material_tagline);
        // La carte sert aussi la section Nouveautés de l'accueil.
        $this->get('/')->assertOk()->assertSee($product->material_tagline);
    }

    public function test_a_variant_colour_resolves_to_a_swatch(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $known = ProductVariant::factory()->for($product)->create(['attributes' => ['couleur' => 'Bordeaux']]);
        $hex = ProductVariant::factory()->for($product)->create(['attributes' => ['Coloris' => '#123ABC']]);
        $unknown = ProductVariant::factory()->for($product)->create(['attributes' => ['couleur' => 'Vermillon crépusculaire']]);
        $sizeOnly = ProductVariant::factory()->for($product)->create(['attributes' => ['taille' => 'M']]);

        $this->assertSame('#6B1F2E', $known->swatch());
        // Échappatoire : un code hexadécimal saisi en admin est repris tel quel.
        $this->assertSame('#123ABC', $hex->swatch());
        // Couleur inconnue : aucune pastille, plutôt qu'une pastille fausse.
        $this->assertNull($unknown->swatch());
        $this->assertNull($sizeOnly->swatch());
    }

    public function test_the_category_page_only_lists_products_from_that_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $otherCategory = Category::factory()->create(['is_active' => true]);

        $inCategory = Product::factory()->for($category)->create(['name' => 'Produit A', 'is_active' => true]);
        $inOther = Product::factory()->for($otherCategory)->create(['name' => 'Produit B', 'is_active' => true]);

        $response = $this->get("/categories/{$category->slug}");

        $response->assertOk();
        $response->assertSee($inCategory->name);
        $response->assertDontSee($inOther->name);
    }

    public function test_an_inactive_category_returns_a_404(): void
    {
        $category = Category::factory()->create(['is_active' => false]);

        $this->get("/categories/{$category->slug}")->assertNotFound();
    }

    public function test_the_category_page_lists_its_active_child_categories(): void
    {
        $parent = Category::factory()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);

        $response = $this->get("/categories/{$parent->slug}");

        $response->assertOk();
        $response->assertSee($child->name);
    }
}

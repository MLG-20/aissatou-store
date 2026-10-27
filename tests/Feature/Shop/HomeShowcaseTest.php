<?php

namespace Tests\Feature\Shop;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Content\Look;
use App\Models\Content\Post;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sections vitrine de l'accueil : bandeau défilant, « Le Look », « L'art
 * d'offrir » (guide par budget) et le Journal.
 */
class HomeShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_marquee_shows_the_editable_phrases_and_hides_when_empty(): void
    {
        SiteSetting::current()->update(['marquee_items' => ['Paiement à la réception', 'Livraison soignée']]);

        $this->get('/')->assertOk()->assertSee('Paiement à la réception')->assertSee('Livraison soignée');

        SiteSetting::current()->update(['marquee_items' => []]);

        $this->get('/')->assertOk()->assertDontSee('marquee-track flex w-max items-center', false);
    }

    public function test_the_marquee_is_hidden_until_the_owner_fills_it(): void
    {
        $this->assertSame([], SiteSetting::current()->marquee_items);

        $this->get('/')->assertOk()->assertDontSee('marquee-track flex w-max items-center', false);
    }

    public function test_la_maison_has_its_own_photo_apart_from_the_login_panel(): void
    {
        SiteSetting::current()->update([
            'auth_image_path' => 'auth/connexion.jpg',
            'story_image_path' => 'story/maison.jpg',
        ]);

        $this->get('/')->assertOk()->assertSee('story/maison.jpg', false)->assertDontSee('auth/connexion.jpg', false);
    }

    public function test_the_look_shows_its_pieces_and_the_full_outfit_price(): void
    {
        $voile = Product::factory()->create(['is_active' => true, 'name' => 'Voile Mousseline', 'price' => 6500]);
        $col = Product::factory()->create(['is_active' => true, 'name' => 'Col Brodé', 'price' => 12000]);
        $retired = Product::factory()->create(['is_active' => false, 'name' => 'Pièce Retirée', 'price' => 99000]);

        $look = Look::create(['title' => 'Soirée de Korité', 'image_path' => 'looks/korite.jpg', 'is_active' => true]);
        $look->hotspots()->create(['product_id' => $voile->id, 'x' => 40, 'y' => 12, 'position' => 0]);
        $look->hotspots()->create(['product_id' => $col->id, 'x' => 70.5, 'y' => 45, 'position' => 1]);
        $look->hotspots()->create(['product_id' => $retired->id, 'x' => 10, 'y' => 90, 'position' => 2]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Soirée de Korité')
            ->assertSee('Voile Mousseline')
            // Plus de points sur la photo : les pièces s'allument tour à tour.
            ->assertDontSee('left: 70.5%; top: 45%', false)
            ->assertSee("spots: JSON.parse('[2]')", false)
            // Une pièce désactivée disparaît du look, et de son total.
            ->assertDontSee('Pièce Retirée')
            ->assertSee('18 500 FCFA');
    }

    public function test_a_hidden_look_or_a_look_without_pieces_is_not_shown(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        Look::create(['title' => 'Look masqué', 'image_path' => 'looks/a.jpg', 'is_active' => false])
            ->hotspots()->create(['product_id' => $product->id, 'x' => 50, 'y' => 50]);
        Look::create(['title' => 'Look sans pièce', 'image_path' => 'looks/b.jpg', 'is_active' => true]);

        $this->get('/')->assertOk()->assertDontSee('Look masqué')->assertDontSee('Look sans pièce');
    }

    public function test_the_gift_guide_splits_the_real_prices_into_three_budgets(): void
    {
        Category::factory()->create(['name' => 'Cadeaux', 'slug' => 'cadeaux', 'parent_id' => null]);

        foreach ([5000, 6000, 6500, 12000, 13000, 14000, 30000, 32000, 35000] as $price) {
            Product::factory()->create(['is_active' => true, 'price' => $price]);
        }
        // Un produit masqué ne déplace pas les seuils.
        Product::factory()->create(['is_active' => false, 'price' => 500000]);

        $budgets = $this->get('/')->assertOk()
            ->assertSee('Un présent qui leur ressemble')
            ->assertSee(e(route('shop.index', ['max_price' => 7000])), false)
            ->assertSee('Voir les coffrets &amp; cadeaux', false)
            ->viewData('giftBudgets');

        $this->assertSame(
            [["Jusqu'à 7 000 FCFA", 3], ['7 000 à 14 000 FCFA', 3], ['Plus de 14 000 FCFA', 3]],
            collect($budgets)->map(fn ($budget) => [$budget['label'], $budget['count']])->all()
        );
    }

    public function test_the_gift_guide_offers_only_budgets_that_have_pieces(): void
    {
        Product::factory()->count(3)->create(['is_active' => true, 'price' => 5000]);

        $budgets = $this->get('/')->assertOk()->viewData('giftBudgets');

        $this->assertSame(
            [["Jusqu'à 5 000 FCFA", 3]],
            collect($budgets)->map(fn ($budget) => [$budget['label'], $budget['count']])->all()
        );
    }

    public function test_the_journal_teaser_lists_the_latest_published_posts(): void
    {
        Post::create(['title' => 'Bien choisir son hijab', 'content' => '<p>Texte</p>', 'is_published' => true, 'published_at' => now()->subDay()]);
        Post::create(['title' => 'Brouillon secret', 'content' => '<p>Texte</p>', 'is_published' => false]);

        $this->get('/')->assertOk()
            ->assertSee('Inspirations &amp; conseils', false)
            ->assertSee('Bien choisir son hijab')
            ->assertDontSee('Brouillon secret');
    }
}

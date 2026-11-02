<?php

namespace Tests\Feature\Marketing;

use App\Enums\Content\LegalDocument;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Content\LegalPage;
use App\Models\Content\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le plan du site donné à Google : uniquement ce qu'une visiteuse peut ouvrir,
 * jamais un brouillon, un produit retiré ou une page privée.
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_public_pages_and_published_content(): void
    {
        $product = Product::factory()->create(['slug' => 'hijab-ivoire', 'is_active' => true]);
        $category = Category::factory()->create(['slug' => 'hijabs', 'is_active' => true]);
        $collection = Collection::create(['name' => 'Ramadan', 'slug' => 'ramadan', 'is_active' => true]);
        $post = Post::create(['title' => 'Bien draper', 'content' => '<p>Texte</p>', 'is_published' => true, 'published_at' => now()->subDay()]);
        LegalPage::for(LegalDocument::cases()[0])->update(['is_published' => true]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        foreach ([
            route('home'),
            route('shop.index'),
            route('shop.contact'),
            route('shop.product', $product),
            route('shop.category', $category),
            route('shop.collection', $collection),
            route('shop.post', $post),
            route('shop.legal', LegalDocument::cases()[0]),
        ] as $url) {
            $response->assertSee('<loc>'.$url.'</loc>', false);
        }

        $response->assertSee('<lastmod>'.$product->updated_at->toDateString().'</lastmod>', false);
    }

    public function test_it_hides_what_a_visitor_cannot_open(): void
    {
        Product::factory()->create(['slug' => 'produit-retire', 'is_active' => false]);
        Category::factory()->create(['slug' => 'rayon-masque', 'is_active' => false]);
        Collection::create(['name' => 'Masquee', 'slug' => 'collection-masquee', 'is_active' => false]);
        Post::create(['title' => 'Brouillon', 'content' => '<p>Texte</p>', 'is_published' => false]);
        Post::create(['title' => 'Parution future', 'content' => '<p>Texte</p>', 'is_published' => true, 'published_at' => now()->addWeek()]);

        $response = $this->get('/sitemap.xml')->assertOk();

        foreach (['produit-retire', 'rayon-masque', 'collection-masquee', 'brouillon', 'parution-future', route('shop.legal', LegalDocument::cases()[0]), 'panier', 'compte', 'admin'] as $hidden) {
            $response->assertDontSee($hidden, false);
        }
    }

    public function test_robots_announces_the_sitemap_with_the_site_address(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);

        $this->assertFileDoesNotExist(public_path('robots.txt'), 'Un fichier statique serait servi à la place de la route.');
    }
}

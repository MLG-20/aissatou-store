<?php

namespace Tests\Feature\Shop;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce que les moteurs de recherche peuvent indexer : les pages de la boutique,
 * jamais le panier, le compte ou le back-office, et une seule adresse par page.
 */
class SearchIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_pages_can_be_indexed_and_declare_their_address(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->create();

        foreach ([route('home'), route('shop.index'), route('shop.product', $product), route('shop.contact')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertHeaderMissing('X-Robots-Tag')
                ->assertSee('<link rel="canonical" href="'.$url.'">', false);
        }
    }

    public function test_private_pages_are_not_indexed_even_without_an_account(): void
    {
        foreach (['shop.cart', 'shop.wishlist', 'login', 'register', 'password.request'] as $name) {
            $this->get(route($name))
                ->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertDontSee('rel="canonical"', false);
        }
    }

    public function test_account_pages_are_not_indexed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account.orders.index'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_back_office_is_not_indexed(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_tracked_and_filtered_links_point_to_the_clean_address(): void
    {
        $catalogue = route('shop.index');

        foreach (['utm_source=tiktok', 'recherche=hijab', 'prix_max=5000&utm_source=instagram', 'page=1'] as $query) {
            $this->get($catalogue.'?'.$query)
                ->assertOk()
                ->assertSee('<link rel="canonical" href="'.$catalogue.'">', false);
        }
    }

    public function test_later_pages_and_new_arrivals_keep_their_own_address(): void
    {
        $catalogue = route('shop.index');

        $this->get($catalogue.'?page=2&utm_source=tiktok')
            ->assertSee('<link rel="canonical" href="'.$catalogue.'?page=2">', false);

        $this->get($catalogue.'?nouveautes=1&prix_max=5000')
            ->assertSee('<link rel="canonical" href="'.$catalogue.'?nouveautes=1">', false);
    }

    public function test_robots_file_blocks_technical_addresses_but_not_pages_carrying_noindex(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['/admin', '/api/', '/webhooks/', '/paiement/', '/livraison/', '/tournee/'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path."\n", $robots);
        }

        // Une page bloquée ici n'est jamais lue : son « noindex » serait ignoré.
        foreach (['/panier', '/compte', '/commande', '/login'] as $path) {
            $this->assertStringNotContainsString('Disallow: '.$path, $robots);
        }
    }
}

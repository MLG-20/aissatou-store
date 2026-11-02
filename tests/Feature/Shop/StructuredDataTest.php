<?php

namespace Tests\Feature\Shop;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductImage;
use App\Models\Catalogue\ProductVariant;
use App\Models\Content\Review;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Données lues par Google pour enrichir un résultat : prix, disponibilité, fil
 * d'Ariane, identité de la boutique. Elles doivent dire la même chose que la page.
 */
class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_gives_its_price_availability_and_photo(): void
    {
        $product = $this->productWithPhoto(['name' => 'Hijab Ivoire', 'price' => 8000, 'description' => '<p>Doux et <b>facile</b>.</p>']);
        ProductVariant::factory()->for($product)->create(['stock' => 3, 'price_override' => null]);

        $data = $this->block($this->get(route('shop.product', $product)), 'Product');

        $this->assertSame('Hijab Ivoire', $data['name']);
        $this->assertSame('Doux et facile.', $data['description']);
        $this->assertSame($product->sku, $data['sku']);
        $this->assertStringStartsWith('http', $data['image'][0]);
        $this->assertSame('Offer', $data['offers']['@type']);
        $this->assertEquals(8000, $data['offers']['price']);
        $this->assertSame('XOF', $data['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
        $this->assertSame(route('shop.product', $product), $data['offers']['url']);
    }

    public function test_a_sold_out_product_says_so(): void
    {
        $product = $this->productWithPhoto();
        ProductVariant::factory()->for($product)->create(['stock' => 0]);

        $data = $this->block($this->get(route('shop.product', $product)), 'Product');

        $this->assertSame('https://schema.org/OutOfStock', $data['offers']['availability']);
    }

    public function test_variants_with_different_prices_give_a_price_range(): void
    {
        $product = $this->productWithPhoto(['price' => 8000]);
        ProductVariant::factory()->for($product)->create(['stock' => 1, 'price_override' => null]);
        ProductVariant::factory()->for($product)->create(['stock' => 1, 'price_override' => 12000]);
        ProductVariant::factory()->for($product)->create(['stock' => 1, 'price_override' => 99000, 'is_active' => false]);

        $offers = $this->block($this->get(route('shop.product', $product)), 'Product')['offers'];

        $this->assertSame('AggregateOffer', $offers['@type']);
        $this->assertEquals(8000, $offers['lowPrice']);
        $this->assertEquals(12000, $offers['highPrice']);
        $this->assertSame(2, $offers['offerCount']);
    }

    public function test_shop_reviews_are_never_shown_as_a_product_rating(): void
    {
        Review::create(['author_name' => 'Awa', 'comment' => 'Parfait', 'rating' => 5, 'is_published' => true]);
        $product = $this->productWithPhoto();

        $this->get(route('shop.product', $product))->assertDontSee('aggregateRating', false);
    }

    public function test_a_product_without_photo_gives_no_product_block(): void
    {
        $product = Product::factory()->create();

        $this->assertNull($this->block($this->get(route('shop.product', $product)), 'Product', required: false));
    }

    public function test_product_and_category_pages_give_their_breadcrumb(): void
    {
        $parent = Category::factory()->create(['name' => 'Hijabs', 'slug' => 'hijabs', 'is_active' => true]);
        $child = Category::factory()->create(['name' => 'Jersey', 'slug' => 'jersey', 'parent_id' => $parent->id, 'is_active' => true]);
        $product = $this->productWithPhoto(['name' => 'Hijab Ivoire', 'category_id' => $child->id]);

        $items = $this->block($this->get(route('shop.product', $product)), 'BreadcrumbList')['itemListElement'];

        $this->assertSame(['Accueil', 'Hijabs', 'Jersey', 'Hijab Ivoire'], array_column($items, 'name'));
        $this->assertSame([1, 2, 3, 4], array_column($items, 'position'));
        $this->assertSame(route('shop.category', $parent), $items[1]['item']);

        $items = $this->block($this->get(route('shop.category', $child)), 'BreadcrumbList')['itemListElement'];

        $this->assertSame(['Accueil', 'Hijabs', 'Jersey'], array_column($items, 'name'));
    }

    public function test_the_home_page_presents_the_shop_with_real_contact_details_only(): void
    {
        SiteSetting::current()->update([
            'contact_whatsapp' => '+221 77 123 45 67',
            'social_instagram' => 'https://instagram.com/aissatou',
            'social_facebook' => null,
        ]);

        $store = $this->block($this->get(route('home')), 'OnlineStore');

        $this->assertSame(config('app.name'), $store['name']);
        $this->assertSame(route('home'), $store['url']);
        $this->assertSame('+221771234567', $store['telephone']);
        $this->assertSame(['https://instagram.com/aissatou'], $store['sameAs']);
        $this->assertStringEndsWith('/brand/symbole-512.png', $store['logo']);
    }

    public function test_the_placeholder_phone_number_is_not_announced(): void
    {
        SiteSetting::current()->update(['contact_whatsapp' => '+221 XX XXX XX XX']);

        $this->assertArrayNotHasKey('telephone', $this->block($this->get(route('home')), 'OnlineStore'));
    }

    public function test_a_product_name_cannot_break_out_of_the_data_block(): void
    {
        $product = $this->productWithPhoto(['name' => '</script><script>alert(1)</script>']);

        $response = $this->get(route('shop.product', $product));

        $response->assertDontSee('</script><script>alert(1)', false);
        $this->assertSame('</script><script>alert(1)</script>', $this->block($response, 'Product')['name']);
    }

    private function productWithPhoto(array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);
        ProductImage::factory()->for($product)->create(['is_primary' => true]);

        return $product->fresh();
    }

    /** @return ?array<string, mixed> Le bloc du type demandé, décodé comme le ferait Google. */
    private function block(TestResponse $response, string $type, bool $required = true): ?array
    {
        $response->assertOk();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $matches);

        foreach ($matches[1] as $json) {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if (($data['@type'] ?? null) === $type) {
                $this->assertSame('https://schema.org', $data['@context']);

                return $data;
            }
        }

        $this->assertFalse($required, 'Bloc '.$type.' absent de la page.');

        return null;
    }
}

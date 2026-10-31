<?php

namespace Tests\Feature\Marketing;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductImage;
use App\Models\Catalogue\ProductVariant;
use App\Services\ImageVariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * Flux catalogue lu par Meta (tag produits Instagram/Facebook) et Google
 * Merchant Center : une ligne par déclinaison, données publiques seulement.
 */
class ProductFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_one_line_per_variant_grouped_under_its_product(): void
    {
        $category = Category::factory()->create(['slug' => 'hijabs']);
        $product = $this->productWithImage(['name' => 'Voile Mousseline', 'sku' => 'VM', 'price' => 6500, 'category_id' => $category->id]);
        ProductVariant::factory()->for($product)->create(['sku' => 'VM-IV', 'attributes' => ['couleur' => 'Ivoire'], 'stock' => 3]);
        ProductVariant::factory()->for($product)->create(['sku' => 'VM-BX', 'attributes' => ['couleur' => 'Bordeaux'], 'stock' => 0, 'price_override' => 7000]);
        ProductVariant::factory()->for($product)->create(['sku' => 'VM-OFF', 'is_active' => false]);

        $items = $this->items();

        $this->assertCount(2, $items);
        $ivoire = $items['VM-IV'];
        $this->assertSame('VM', (string) $ivoire->item_group_id);
        $this->assertSame('Voile Mousseline Ivoire', (string) $ivoire->title);
        $this->assertSame('6500 XOF', (string) $ivoire->price);
        $this->assertSame('in_stock', (string) $ivoire->availability);
        $this->assertSame('Ivoire', (string) $ivoire->color);
        $this->assertSame(route('shop.product', $product), (string) $ivoire->link);
        $this->assertStringEndsWith('-share.jpg', (string) $ivoire->image_link);
        $this->assertSame('Apparel & Accessories > Clothing Accessories > Scarves & Shawls', (string) $ivoire->google_product_category);

        // Rupture : gardée, marquée, avec son propre prix.
        $this->assertSame('out_of_stock', (string) $items['VM-BX']->availability);
        $this->assertSame('7000 XOF', (string) $items['VM-BX']->price);
    }

    public function test_a_colour_already_in_the_name_is_not_repeated(): void
    {
        $product = $this->productWithImage(['name' => 'Hijab Jersey Ivoire']);
        ProductVariant::factory()->for($product)->create(['sku' => 'HJ-IV', 'attributes' => ['couleur' => 'Ivoire']]);

        $this->assertSame('Hijab Jersey Ivoire', (string) $this->items()['HJ-IV']->title);
    }

    public function test_inactive_products_and_products_without_photo_are_left_out(): void
    {
        $this->productWithImage(['sku' => 'OFF', 'is_active' => false]);
        Product::factory()->create(['sku' => 'NOPHOTO']);
        $this->productWithImage(['sku' => 'SIMPLE', 'price' => 12000]);

        $items = $this->items();

        $this->assertSame(['SIMPLE'], array_keys($items));
        // Sans déclinaison : pas de suivi de stock, toujours disponible.
        $this->assertSame('in_stock', (string) $items['SIMPLE']->availability);
    }

    public function test_the_feed_contains_no_customer_data_and_is_not_indexed(): void
    {
        $this->productWithImage();

        $this->get(route('marketing.product-feed'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertDontSee('@');
    }

    /** @return array<string, SimpleXMLElement> lignes du flux, par g:id */
    private function items(): array
    {
        $xml = new SimpleXMLElement($this->get(route('marketing.product-feed'))->assertOk()->getContent());
        $items = [];

        foreach ($xml->channel->item as $item) {
            $g = $item->children('http://base.google.com/ns/1.0');
            $items[(string) $g->id] = $g;
        }

        return $items;
    }

    private function productWithImage(array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);
        $image = ProductImage::factory()->for($product)->create(['is_primary' => true]);
        // Version de partage déjà générée, comme après un upload.
        Storage::disk('public')->put(app(ImageVariantGenerator::class)->sizedPath($image->path, ImageVariantGenerator::SHARE), 'jpeg');

        return $product;
    }
}

<?php

namespace Tests\Feature\Shop;

use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Content\SiteSetting;
use App\Services\ImageVariantGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Aperçu d'un lien partagé (WhatsApp, Facebook, Instagram, TikTok) et bouton
 * « Partager » : la visibilité passe d'abord par les liens que les clientes
 * s'envoient.
 */
class SharePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_link_shows_its_name_price_and_availability(): void
    {
        $product = Product::factory()->create(['name' => 'Hijab Jersey Ivoire', 'price' => 8000, 'description' => '<p>Doux et facile à draper.</p>']);
        ProductVariant::factory()->for($product)->create(['stock' => 0]);

        $this->get(route('shop.product', $product))
            ->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="og:title" content="Hijab Jersey Ivoire · 8 000 FCFA">', false)
            ->assertSee('<meta property="og:description" content="Doux et facile à draper.">', false)
            ->assertSee('<meta property="product:price:amount" content="8000">', false)
            ->assertSee('<meta property="product:price:currency" content="XOF">', false)
            ->assertSee('<meta property="product:availability" content="out of stock">', false);
    }

    public function test_every_page_has_a_preview_image_and_text(): void
    {
        SiteSetting::current()->update(['footer_tagline' => 'L\'élégance voilée, pensée pour vous.']);

        $this->get(route('shop.contact'))
            ->assertSee('<meta property="og:image" content="'.url('/brand/partage.jpg').'">', false)
            ->assertSee('<meta property="og:description" content="L&#039;élégance voilée, pensée pour vous.">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_each_photo_gets_a_jpeg_preview_next_to_its_webp_sizes(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs('products', UploadedFile::fake()->image('voile.png', 1600, 1600), 'voile.png');
        $generator = app(ImageVariantGenerator::class);

        $generator->generate('products/voile.png');

        $share = $generator->sizedPath('products/voile.png', ImageVariantGenerator::SHARE);
        $this->assertSame('products/voile-share.jpg', $share);
        $this->assertSame('image/jpeg', Storage::disk('public')->mimeType($share));
        $this->assertSame(1200, getimagesizefromstring(Storage::disk('public')->get($share))[0]);
        Storage::disk('public')->assertExists('products/voile-large.webp');

        $generator->forget('products/voile.png');
        Storage::disk('public')->assertMissing($share);
    }

    public function test_the_product_page_offers_to_share_it(): void
    {
        $product = Product::factory()->create(['name' => 'Voile Mousseline', 'price' => 6500]);

        $this->get(route('shop.product', $product))
            ->assertSee('Partager')
            ->assertSee('https://wa.me/?text='.rawurlencode('Voile Mousseline · 6 500 FCFA '.route('shop.product', $product)), false);
    }
}

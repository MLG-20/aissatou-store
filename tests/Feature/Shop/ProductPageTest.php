<?php

namespace Tests\Feature\Shop;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Content\SiteSetting;
use App\Models\Delivery\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fiche produit enrichie : réassurance tirée des vrais réglages, volets
 * livraison/paiement, suggestions (même univers, autres univers, vus
 * récemment) et question WhatsApp pré-remplie.
 */
class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassurance_and_folds_come_from_the_real_settings(): void
    {
        DeliveryZone::create(['name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1000, 'delay_label' => '24-48h', 'is_active' => true, 'position' => 1]);
        DeliveryZone::create(['name' => 'Thiès', 'city' => 'Thiès', 'fee' => 2500, 'is_active' => true, 'position' => 2]);
        DeliveryZone::create(['name' => 'Zone fermée', 'city' => 'Nulle part', 'fee' => 500, 'is_active' => false, 'position' => 3]);
        $product = Product::factory()->create(['is_active' => true]);

        $this->get(route('shop.product', $product))
            ->assertOk()
            // La zone la moins chère PARMI les zones actives.
            ->assertSee('Livraison dès 1 000 FCFA')
            ->assertSee('Dakar, 24-48h')
            ->assertSee('2 500 FCFA')
            ->assertDontSee('Zone fermée')
            ->assertSee('Payez à la réception')
            ->assertSee('Paiement à la livraison');
    }

    public function test_the_whatsapp_question_is_prefilled_with_the_product(): void
    {
        SiteSetting::current()->update(['contact_whatsapp' => '+221 77 123 45 67']);
        $product = Product::factory()->create(['is_active' => true, 'name' => 'Voile Mousseline', 'price' => 6500]);

        $this->get(route('shop.product', $product))
            ->assertOk()
            ->assertSee('Une question ?')
            ->assertSee(e(route('shop.whatsapp', ['texte' => 'Bonjour, je suis intéressée par « Voile Mousseline » (6 500 FCFA). '.route('shop.product', $product)])), false);
    }

    public function test_the_whatsapp_question_is_hidden_without_a_number(): void
    {
        SiteSetting::current()->update(['contact_whatsapp' => null]);
        $product = Product::factory()->create(['is_active' => true]);

        $this->get(route('shop.product', $product))->assertOk()->assertDontSee('Une question ?');
    }

    public function test_the_whatsapp_redirect_carries_the_prefilled_text(): void
    {
        SiteSetting::current()->update(['contact_whatsapp' => '+221 77 123 45 67']);

        $this->get(route('shop.whatsapp', ['texte' => 'Bonjour & merci']))
            ->assertRedirect('https://wa.me/221771234567?text=Bonjour%20%26%20merci');

        $this->get(route('shop.whatsapp'))->assertRedirect('https://wa.me/221771234567');

        // Texte tronqué : pas d'URL démesurée.
        $location = $this->get(route('shop.whatsapp', ['texte' => str_repeat('a', 2000)]))->headers->get('Location');
        $this->assertSame(500, strlen(substr($location, strpos($location, '?text=') + 6)));
    }

    public function test_suggestions_split_same_universe_and_other_universes(): void
    {
        $hijabs = Category::factory()->create(['name' => 'Hijabs']);
        $cols = Category::factory()->create(['name' => 'Cols']);
        $parfums = Category::factory()->create(['name' => 'Parfums']);

        $product = Product::factory()->for($hijabs)->create(['is_active' => true]);
        Product::factory()->for($hijabs)->create(['is_active' => true, 'name' => 'Hijab Jumeau']);
        Product::factory()->for($hijabs)->create(['is_active' => false, 'name' => 'Hijab Retiré']);
        Product::factory()->for($cols)->create(['is_active' => true, 'name' => 'Col Perlé']);
        Product::factory()->for($cols)->create(['is_active' => true, 'name' => 'Col Brodé', 'created_at' => now()->subDay()]);
        $soldOut = Product::factory()->for($parfums)->create(['is_active' => true, 'name' => 'Parfum Épuisé']);
        ProductVariant::factory()->for($soldOut)->create(['stock' => 0]);

        $response = $this->get(route('shop.product', $product))->assertOk();

        $response->assertSee('Vous aimerez aussi')
            ->assertSee('Hijab Jumeau')
            ->assertDontSee('Hijab Retiré');

        // « Complétez votre tenue » : une pièce EN STOCK par autre univers.
        $complete = $response->viewData('completeTheLook');
        $this->assertSame(['Col Perlé'], $complete->pluck('name')->all());
    }

    public function test_recently_viewed_lists_previous_pieces_newest_first(): void
    {
        [$first, $second, $current] = Product::factory()->count(3)->create(['is_active' => true]);

        $this->get(route('shop.product', $first));
        $this->get(route('shop.product', $second));

        // Pas encore d'historique à la première visite.
        $this->assertTrue($this->get(route('shop.product', $first))->viewData('recentlyViewed')->pluck('id')->doesntContain($first->id));

        $recent = $this->get(route('shop.product', $current))->viewData('recentlyViewed');

        // La pièce affichée ne se recommande pas elle-même ; ordre du plus récent.
        $this->assertSame([$first->id, $second->id], $recent->pluck('id')->all());
    }
}

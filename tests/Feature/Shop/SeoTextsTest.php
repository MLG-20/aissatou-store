<?php

namespace Tests\Feature\Shop;

use App\Filament\Pages\SiteSettings;
use App\Models\Admin;
use App\Models\Catalogue\Product;
use App\Models\Content\HeroSlide;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Titres et descriptions montrés par Google, modifiables par la cliente. */
class SeoTextsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_changes_the_home_title_and_description(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(SiteSettings::class)
            ->set('data.seo_home_title', 'Hijabs à Dakar')
            ->set('data.seo_home_description', 'Livraison à Dakar et dans tout le Sénégal.')
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('home'))
            ->assertSee('<title>Hijabs à Dakar</title>', false)
            ->assertSee('<meta name="description" content="Livraison à Dakar et dans tout le Sénégal.">', false);
    }

    public function test_a_title_google_would_cut_is_refused(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(SiteSettings::class)
            ->set('data.seo_home_title', str_repeat('a', 71))
            ->call('save')
            ->assertHasErrors('data.seo_home_title');
    }

    public function test_each_main_page_has_its_own_description(): void
    {
        $descriptions = [];

        foreach (['home', 'shop.index', 'shop.collections', 'shop.lookbook', 'shop.journal', 'shop.about', 'shop.contact'] as $name) {
            $html = $this->get(route($name))->assertOk()->getContent();
            preg_match('/<meta name="description" content="([^"]+)">/', $html, $match);
            $descriptions[$name] = $match[1] ?? null;
        }

        $this->assertNotContains(null, $descriptions);
        $this->assertCount(count($descriptions), array_unique($descriptions));
    }

    public function test_a_product_without_description_falls_back_on_the_shop_signature(): void
    {
        SiteSetting::current()->update(['footer_tagline' => 'Signature de la boutique']);
        $product = Product::factory()->create(['description' => '']);

        $this->get(route('shop.product', $product))
            ->assertSee('<meta name="description" content="Signature de la boutique">', false);
    }

    public function test_the_home_page_has_a_single_main_heading_whatever_the_slides(): void
    {
        foreach ([1, 2, 3] as $position) {
            HeroSlide::create(['title' => 'Diapositive '.$position, 'image_path' => 'hero/'.$position.'.jpg', 'position' => $position, 'is_active' => true]);
        }

        $response = $this->get(route('home'))->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertSee('Diapositive 3');
    }
}

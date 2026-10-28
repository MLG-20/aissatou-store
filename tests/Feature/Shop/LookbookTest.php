<?php

namespace Tests\Feature\Shop;

use App\Filament\Resources\Content\LookResource\Pages\EditLook;
use App\Models\Admin;
use App\Models\Catalogue\Product;
use App\Models\Content\Look;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le Lookbook : tous les looks publiés en plein écran, une adresse par look
 * (à partager), l'accueil n'en montre qu'un aperçu choisi.
 */
class LookbookTest extends TestCase
{
    use RefreshDatabase;

    private Product $voile;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->voile = Product::factory()->create(['is_active' => true, 'name' => 'Voile Mousseline', 'price' => 6500]);
    }

    private function look(array $attributes): Look
    {
        $look = Look::create($attributes + ['image_path' => 'looks/photo.jpg', 'is_active' => true]);
        $look->hotspots()->create(['product_id' => $this->voile->id, 'x' => 50, 'y' => 50]);

        return $look;
    }

    public function test_the_lookbook_shows_every_published_look_with_its_pieces(): void
    {
        $this->look(['title' => 'Lumière dorée', 'chapter' => 'Korité 2026', 'mood' => 'Une soie qui accroche le soleil.', 'show_on_home' => false]);
        $this->look(['title' => 'Look masqué', 'is_active' => false]);
        $this->look(['title' => 'Vidéo en préparation', 'media_type' => 'video', 'image_path' => null, 'video_source_path' => 'looks/videos/sources/a.mp4']);

        $this->get(route('shop.lookbook'))
            ->assertOk()
            ->assertSee('Lumière dorée')
            ->assertSee('Korité 2026')
            ->assertSee('Une soie qui accroche le soleil.')
            ->assertSee('Voile Mousseline')
            ->assertSee('1 tenue')
            ->assertDontSee('Look masqué')
            ->assertDontSee('Vidéo en préparation');
    }

    public function test_each_look_has_its_own_address_with_a_share_preview(): void
    {
        $look = $this->look(['title' => 'Lumière dorée', 'mood' => 'Une soie qui accroche le soleil.']);
        $twin = $this->look(['title' => 'Lumière dorée']);

        $this->assertSame('lumiere-doree', $look->slug);
        $this->assertSame('lumiere-doree-2', $twin->slug);

        $this->get('/lookbook/lumiere-doree')
            ->assertOk()
            ->assertSee('<title>Lumière dorée — Lookbook — '.config('app.name').'</title>', false)
            ->assertSee('<meta property="og:description" content="Une soie qui accroche le soleil.">', false)
            ->assertSee('og:image', false);
    }

    public function test_a_hidden_or_unknown_look_has_no_page(): void
    {
        $this->look(['title' => 'Look masqué', 'is_active' => false]);

        $this->get('/lookbook/look-masque')->assertNotFound();
        $this->get('/lookbook/nexiste-pas')->assertNotFound();
    }

    public function test_the_pieces_can_be_bought_or_ordered_on_whatsapp_from_the_lookbook(): void
    {
        SiteSetting::current()->update(['contact_whatsapp' => '+221 77 123 45 67']);
        $this->look(['title' => 'Lumière dorée']);

        $this->get(route('shop.lookbook'))
            ->assertOk()
            ->assertSee('action="'.route('shop.cart.store').'"', false)
            ->assertSee('name="product_id" value="'.$this->voile->id.'"', false)
            ->assertSee('Commander ce look sur WhatsApp');
    }

    public function test_the_home_previews_only_the_chosen_looks_and_leads_to_the_lookbook(): void
    {
        $this->look(['title' => 'Sur l\'accueil', 'show_on_home' => true]);
        $this->look(['title' => 'Lookbook seulement', 'show_on_home' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Sur l&#039;accueil', false)
            ->assertDontSee('Lookbook seulement')
            ->assertSee(route('shop.lookbook.look', 'sur-laccueil'), false)
            ->assertSee('Voir le Lookbook');
    }

    public function test_the_lookbook_is_in_the_navigation(): void
    {
        $this->get(route('shop.index'))->assertOk()->assertSee('href="'.route('shop.lookbook').'"', false);
    }

    public function test_the_owner_can_change_the_address_of_a_look(): void
    {
        $look = $this->look(['title' => 'Lumière dorée']);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(EditLook::class, ['record' => $look->getRouteKey()])
            ->fillForm(['slug' => 'soiree-doree', 'show_on_home' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('soiree-doree', $look->fresh()->slug);
        $this->assertFalse($look->fresh()->show_on_home);
    }
}

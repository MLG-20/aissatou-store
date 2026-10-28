<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Content\LookResource\Pages\CreateLook;
use App\Models\Admin;
use App\Models\Catalogue\Product;
use App\Models\Content\Look;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Look en lien YouTube : l'identifiant est tiré du lien collé, l'image
 * d'aperçu copiée en local, le lecteur ne se charge que dans le Lookbook.
 */
class LookYouTubeTest extends TestCase
{
    use RefreshDatabase;

    /** Image d'aperçu factice, assez lourde pour passer pour une vraie. */
    private function poster(): string
    {
        $image = imagecreatetruecolor(200, 356);
        for ($x = 0; $x < 200; $x += 2) {
            for ($y = 0; $y < 356; $y += 2) {
                imagesetpixel($image, $x, $y, random_int(0, 0xFFFFFF));
            }
        }
        ob_start();
        imagejpeg($image, null, 90);

        return ob_get_clean();
    }

    public function test_an_admin_adds_a_look_from_a_youtube_short_link(): void
    {
        Storage::fake('public');
        Http::fake(['i.ytimg.com/vi/aBcDeFgHiJ0/oardefault.jpg' => Http::response($this->poster())]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateLook::class)
            ->fillForm([
                'title' => 'Jersey du matin',
                'media_type' => 'youtube',
                'youtube_id' => 'https://youtube.com/shorts/aBcDeFgHiJ0?si=partage',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $look = Look::sole();
        $this->assertSame('aBcDeFgHiJ0', $look->youtube_id);
        $this->assertTrue($look->isYouTube());
        // Aperçu copié en local, avec ses variantes WebP.
        $this->assertSame('looks/youtube/aBcDeFgHiJ0.jpg', $look->image_path);
        $this->assertTrue(Storage::disk('public')->exists('looks/youtube/aBcDeFgHiJ0-large.webp'));
        Http::assertSentCount(1);
    }

    public function test_a_link_that_is_not_youtube_is_refused(): void
    {
        Http::fake();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateLook::class)
            ->fillForm([
                'title' => 'TikTok',
                'media_type' => 'youtube',
                'youtube_id' => 'https://www.tiktok.com/@boutique/video/1234567890',
            ])
            ->call('create')
            ->assertHasFormErrors(['youtube_id']);

        $this->assertDatabaseCount('looks', 0);
    }

    public function test_youtube_unreachable_still_publishes_the_look(): void
    {
        Http::fake(['i.ytimg.com/*' => Http::response('', 404)]);

        $look = Look::create(['title' => 'Sans aperçu', 'media_type' => 'youtube', 'youtube_id' => 'aBcDeFgHiJ0']);

        $this->assertNull($look->fresh()->image_path);
        $this->assertTrue(Look::query()->displayable()->whereKey($look->id)->exists());
    }

    public function test_the_lookbook_loads_the_player_only_on_screen_and_the_home_shows_the_preview(): void
    {
        Http::fake();
        $product = Product::factory()->create(['is_active' => true]);
        $look = Look::create(['title' => 'Jersey du matin', 'media_type' => 'youtube', 'youtube_id' => 'aBcDeFgHiJ0', 'image_path' => 'looks/youtube/aBcDeFgHiJ0.jpg']);
        $look->hotspots()->create(['product_id' => $product->id, 'x' => 50, 'y' => 50]);

        $this->get(route('shop.lookbook'))
            ->assertOk()
            ->assertSee('<template x-if="active === 0 && ! still">', false)
            ->assertSee('https://www.youtube-nocookie.com/embed/aBcDeFgHiJ0?', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('Voir la vidéo « Jersey du matin » dans le Lookbook', false)
            ->assertDontSee('youtube-nocookie.com', false);
    }
}

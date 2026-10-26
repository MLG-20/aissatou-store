<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Catalogue\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\Content\HeroSlideResource\Pages\ManageHeroSlides;
use App\Models\Admin;
use App\Models\Catalogue\Category;
use App\Models\Content\HeroSlide;
use App\Support\ImageSlot;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Charte photo (App\Support\ImageSlot) : photos trop petites refusées à
 * l'envoi, photos déjà en ligne signalées, hero responsive avec point focal
 * et version téléphone.
 */
class ImageCharterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_photo_below_the_minimum_is_refused_with_a_clear_message(): void
    {
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Voile flou',
                'slug' => 'voile-flou',
                'category_id' => Category::factory()->create()->id,
                'price' => 5000,
                'images' => [
                    ['path' => [UploadedFile::fake()->image('petite.jpg', 600, 800)], 'is_primary' => true, 'position' => 0],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertDatabaseMissing('products', ['slug' => 'voile-flou']);
        $this->assertStringContainsString('1 050 × 1 400 px', ImageSlot::Product->configure(FileUpload::make('x'))->getValidationMessages()['dimensions']);
    }

    public function test_a_hero_photo_below_full_hd_is_refused(): void
    {
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ManageHeroSlides::class)
            ->callAction('create', data: [
                'image_path' => [UploadedFile::fake()->image('hero.jpg', 1280, 720)],
                'title' => 'Trop petite',
                'focal_x' => 50,
                'focal_y' => 50,
                'position' => 0,
                'is_active' => true,
            ])
            ->assertHasActionErrors(['image_path']);

        Livewire::test(ManageHeroSlides::class)
            ->callAction('create', data: [
                'image_path' => [UploadedFile::fake()->image('hero.jpg', 1920, 1080)],
                'mobile_image_path' => [UploadedFile::fake()->image('hero-mobile.jpg', 1080, 1350)],
                'title' => 'Bien cadrée',
                'focal_x' => 30,
                'focal_y' => 20,
                'position' => 0,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $slide = HeroSlide::where('title', 'Bien cadrée')->firstOrFail();
        $this->assertSame([30, 20], [$slide->focal_x, $slide->focal_y]);
        // Variantes générées pour les deux photos, dont la grande 2 400 px.
        Storage::disk('public')->assertExists(preg_replace('/\.\w+$/', '-xlarge.webp', $slide->image_path));
        Storage::disk('public')->assertExists(preg_replace('/\.\w+$/', '-medium.webp', $slide->mobile_image_path));
    }

    public function test_photos_already_online_are_flagged_not_blocked(): void
    {
        Storage::fake('public');
        $small = UploadedFile::fake()->image('ancienne.jpg', 500, 500)->store('hero', 'public');
        $sharp = UploadedFile::fake()->image('nette.jpg', 2560, 1440)->store('hero', 'public');

        $this->assertTrue(ImageSlot::Hero->isBelowMinimum($small));
        $this->assertFalse(ImageSlot::Hero->isBelowMinimum($sharp));
        $this->assertFalse(ImageSlot::Hero->isBelowMinimum(null));

        $this->actingAs(Admin::factory()->create(), 'admin');
        HeroSlide::create(['title' => 'Ancienne', 'image_path' => $small, 'position' => 0, 'is_active' => true]);

        Livewire::test(ManageHeroSlides::class)->assertSee('À remplacer');
    }

    public function test_the_hero_serves_a_responsive_image_with_its_focal_point_and_phone_version(): void
    {
        Storage::fake('public');
        HeroSlide::create([
            'title' => 'Korité',
            'image_path' => UploadedFile::fake()->image('hero.jpg', 1920, 1080)->store('hero', 'public'),
            'mobile_image_path' => UploadedFile::fake()->image('mobile.jpg', 1080, 1350)->store('hero', 'public'),
            'focal_x' => 70,
            'focal_y' => 25,
            'position' => 0,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<source', false)
            ->assertSee('media="(max-width: 767px)"', false)
            ->assertSee('-xlarge.webp 2400w', false)
            ->assertSee('--focal: 70% 25%', false)
            // Version téléphone dédiée : le point focal ne vaut que sur grand écran.
            ->assertSee('md:[object-position:var(--focal)]', false);
    }
}

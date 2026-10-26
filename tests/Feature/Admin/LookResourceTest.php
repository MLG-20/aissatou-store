<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Content\LookResource\Pages\CreateLook;
use App\Filament\Resources\Content\LookResource\Pages\EditLook;
use App\Models\Admin;
use App\Models\Catalogue\Product;
use App\Models\Content\Look;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LookResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_creates_a_look_then_places_its_pieces(): void
    {
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(), 'admin');
        $product = Product::factory()->create(['is_active' => true]);

        Livewire::test(CreateLook::class)
            ->fillForm([
                'title' => 'Soirée de Korité',
                'image_path' => [UploadedFile::fake()->image('look.jpg', 800, 1200)],
                'position' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $look = Look::firstOrFail();
        $this->assertTrue(Storage::disk('public')->exists($look->image_path));
        // Variantes WebP générées pour la page d'accueil.
        $this->assertTrue(Storage::disk('public')->exists(preg_replace('/\.\w+$/', '-large.webp', $look->image_path)));

        Livewire::test(EditLook::class, ['record' => $look->getRouteKey()])
            ->set('data.hotspots', [['product_id' => $product->id, 'x' => 35, 'y' => 60]])
            ->call('save')
            ->assertHasNoFormErrors();

        $hotspot = $look->hotspots()->sole();
        $this->assertSame($product->id, $hotspot->product_id);
        $this->assertSame([35.0, 60.0], [$hotspot->x, $hotspot->y]);
        // La photo est conservée quand le champ d'upload reste vide.
        $this->assertSame($look->image_path, $look->fresh()->image_path);
    }
}

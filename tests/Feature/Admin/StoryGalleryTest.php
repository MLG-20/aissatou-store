<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SiteSettings;
use App\Models\Admin;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Diaporama « La Maison » : plusieurs photos, dans l'ordre choisi en admin.
 */
class StoryGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_saves_several_photos_in_her_order(): void
    {
        Storage::fake('public');
        // Filament n'affiche que les fichiers réellement présents.
        Storage::disk('public')->putFileAs('story', UploadedFile::fake()->image('a.jpg', 40, 50), 'a.jpg');
        Storage::disk('public')->putFileAs('story', UploadedFile::fake()->image('b.jpg', 40, 50), 'b.jpg');
        $this->actingAs(Admin::factory()->create(), 'admin');
        SiteSetting::current()->update(['story_images' => ['story/a.jpg', 'story/b.jpg']]);

        $page = Livewire::test(SiteSettings::class);
        $this->assertSame(['story/a.jpg', 'story/b.jpg'], array_values($page->get('data.story_images')));

        // Glisser-déposer : b passe devant a.
        $page->set('data.story_images', ['x' => 'story/b.jpg', 'y' => 'story/a.jpg'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['story/b.jpg', 'story/a.jpg'], SiteSetting::current()->fresh()->story_images);
    }
}

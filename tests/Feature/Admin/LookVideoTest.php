<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Content\LookResource\Pages\CreateLook;
use App\Models\Admin;
use App\Models\Catalogue\Product;
use App\Models\Content\Look;
use App\Services\VideoProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * « Le Look » en vidéo : 30 s au plus, allégée en arrière-plan (ffmpeg), le
 * look n'apparaît sur l'accueil qu'une fois la vidéo prête.
 */
class LookVideoTest extends TestCase
{
    use RefreshDatabase;

    /** Petite vidéo de test (mire) de la durée voulue. */
    private function clip(int $seconds): string
    {
        if (! app(VideoProcessor::class)->available()) {
            $this->markTestSkipped('ffmpeg absent de cette machine.');
        }

        $path = tempnam(sys_get_temp_dir(), 'clip').'.mp4';
        Process::run(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', "testsrc=duration={$seconds}:size=320x568:rate=5", '-pix_fmt', 'yuv420p', $path])->throw();

        return $path;
    }

    public function test_an_admin_creates_a_video_look_prepared_in_the_background(): void
    {
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateLook::class)
            ->fillForm([
                'title' => 'Bleu profond',
                'media_type' => 'video',
                'video_source_path' => [UploadedFile::fake()->createWithContent('look.mp4', file_get_contents($this->clip(3)))],
                'position' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // File synchrone en test : la vidéo est déjà traitée.
        $look = Look::sole();
        $disk = Storage::disk('public');
        $this->assertSame(Look::VIDEO_READY, $look->video_status);
        $this->assertTrue($disk->exists($look->video_path));
        $this->assertStringEndsWith('.jpg', $look->image_path);
        $this->assertTrue($disk->exists(preg_replace('/\.jpg$/', '-large.webp', $look->image_path)));
        // L'original, lourd, n'est pas conservé.
        $this->assertNull($look->video_source_path);
        $this->assertSame([], $disk->files('looks/videos/sources'));
    }

    public function test_a_video_longer_than_30_seconds_is_refused(): void
    {
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateLook::class)
            ->fillForm([
                'title' => 'Trop long',
                'media_type' => 'video',
                'video_source_path' => [UploadedFile::fake()->createWithContent('long.mp4', file_get_contents($this->clip(32)))],
            ])
            ->call('create')
            ->assertHasFormErrors(['video_source_path']);

        $this->assertDatabaseCount('looks', 0);
    }

    public function test_an_unreadable_video_is_marked_failed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('looks/videos/sources/abime.mp4', 'pas une vidéo');

        $look = Look::create(['title' => 'Abîmé', 'media_type' => 'video', 'video_source_path' => 'looks/videos/sources/abime.mp4']);

        $this->assertSame(Look::VIDEO_FAILED, $look->fresh()->video_status);
        $this->assertNotNull($look->fresh()->video_error);
    }

    public function test_the_home_shows_a_ready_video_and_waits_for_one_in_preparation(): void
    {
        Queue::fake();
        $product = Product::factory()->create(['is_active' => true]);

        $ready = Look::create(['title' => 'Vidéo prête', 'media_type' => 'video', 'video_path' => 'looks/videos/pret.mp4', 'image_path' => 'looks/videos/pret.jpg', 'video_status' => Look::VIDEO_READY]);
        $pending = Look::create(['title' => 'Vidéo en cours', 'media_type' => 'video', 'video_source_path' => 'looks/videos/sources/encours.mp4']);
        $ready->hotspots()->create(['product_id' => $product->id, 'x' => 50, 'y' => 50]);
        $pending->hotspots()->create(['product_id' => $product->id, 'x' => 50, 'y' => 50]);

        $this->assertSame(Look::VIDEO_PROCESSING, $pending->video_status);

        $this->get('/')
            ->assertOk()
            ->assertSee('Vidéo prête')
            ->assertSee('src="/storage/looks/videos/pret.mp4"', false)
            ->assertSee('preload="none"', false)
            ->assertSee('kinds: JSON.parse(\'[\u0022video\u0022]\')', false)
            ->assertDontSee('Vidéo en cours');
    }

    public function test_the_video_option_is_off_when_the_server_cannot_process_videos(): void
    {
        config(['media.ffmpeg' => '/introuvable/ffmpeg']);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateLook::class)->assertSee('Vidéo indisponible');
    }
}

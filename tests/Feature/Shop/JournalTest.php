<?php

namespace Tests\Feature\Shop;

use App\Filament\Resources\Content\PostResource\Pages\CreatePost;
use App\Models\Admin;
use App\Models\Catalogue\Product;
use App\Models\Content\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_journal_lists_published_posts_only(): void
    {
        Post::create([
            'title' => 'Article visible', 'content' => '<p>Bonjour</p>',
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        Post::create([
            'title' => 'Brouillon', 'content' => '<p>Brouillon</p>',
            'is_published' => false, 'published_at' => now()->subDay(),
        ]);

        $response = $this->get('/journal');

        $response->assertOk();
        $response->assertSee('Article visible');
        $response->assertDontSee('Brouillon');
    }

    public function test_a_draft_or_future_post_returns_404(): void
    {
        $draft = Post::create([
            'title' => 'Brouillon', 'content' => '<p>x</p>',
            'is_published' => false, 'published_at' => now()->subDay(),
        ]);
        $future = Post::create([
            'title' => 'Futur', 'content' => '<p>x</p>',
            'is_published' => true, 'published_at' => now()->addDay(),
        ]);

        $this->get('/journal/'.$draft->slug)->assertNotFound();
        $this->get('/journal/'.$future->slug)->assertNotFound();
    }

    public function test_a_post_shows_its_featured_products(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $post = Post::create([
            'title' => 'Avec produits', 'content' => '<p>x</p>',
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product);

        $this->get('/journal/'.$post->slug)
            ->assertOk()
            ->assertSee('Les pièces de cet article')
            ->assertSee($product->name);
    }

    public function test_scripts_are_stripped_from_the_rendered_content(): void
    {
        $post = Post::create([
            'title' => 'Sécurisé', 'content' => '<p>OK</p><script>alert("xss")</script>',
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);

        $response = $this->get('/journal/'.$post->slug);

        $response->assertOk();
        $response->assertSee('OK');
        $response->assertDontSee('<script>', false);
    }

    public function test_dangerous_attributes_and_links_are_removed_but_formatting_is_kept(): void
    {
        $post = Post::create([
            'title' => 'Attributs',
            'content' => '<p onmouseover="alert(1)">Texte</p>'
                .'<a href="javascript:alert(2)">piège</a>'
                .'<a href="https://example.com/page" target="_blank">lien</a>'
                .'<h2 style="color:red">Titre</h2><img src="x" onerror="alert(3)">',
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);

        $html = $post->safeContent();

        $this->assertStringNotContainsString('onmouseover', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringContainsString('<p>Texte</p>', $html);
        $this->assertStringContainsString('<h2>Titre</h2>', $html);
        $this->assertStringContainsString('href="https://example.com/page"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $html);
    }

    public function test_admin_can_create_a_post_from_the_panel(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $product = Product::factory()->create();

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Nouvel article',
                'excerpt' => 'Chapeau.',
                'content' => '<p>Corps de l\'article.</p>',
                'products' => [$product->id],
                'is_published' => true,
                'published_at' => now()->format('Y-m-d H:i'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'nouvel-article')->firstOrFail();
        $this->assertTrue($post->products->contains($product));
        $this->get('/journal/nouvel-article')->assertSee('Nouvel article');
    }
}

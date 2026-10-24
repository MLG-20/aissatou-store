<?php

namespace Tests\Feature\Shop;

use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Content\Post;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pages éditoriales retravaillées : collections en chapitres, journal en
 * magazine (couvertures jamais vides), contact (canaux et réseaux), et l'œil
 * des champs mot de passe.
 */
class EditorialPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_read_as_chapters_with_their_on_sale_pieces(): void
    {
        $first = Collection::create(['name' => 'Atelier Nocturne', 'slug' => 'atelier-nocturne', 'is_active' => true, 'position' => 1]);
        $second = Collection::create(['name' => 'Essentiels', 'slug' => 'essentiels', 'is_active' => true, 'position' => 2]);
        $first->products()->attach([
            Product::factory()->create(['is_active' => true])->id,
            Product::factory()->create(['is_active' => true])->id,
            Product::factory()->create(['is_active' => false])->id,
        ]);

        $this->get('/collections')
            ->assertOk()
            ->assertSee('Sommaire')
            ->assertSee('Chapitre 01')
            ->assertSee('Chapitre 02')
            // Pièce désactivée non comptée.
            ->assertSee('2 pièces');

        $this->get('/collections/essentiels')
            ->assertOk()
            ->assertSee('Chapitre 02')
            ->assertSee('Poursuivre la lecture')
            ->assertSee('Atelier Nocturne');
    }

    public function test_a_post_without_cover_borrows_its_product_photo_and_shows_reading_time(): void
    {
        $product = Product::factory()->hasImages(1, ['is_primary' => true])->create(['is_active' => true]);
        $post = Post::create([
            'title' => 'Bien choisir son hijab', 'excerpt' => 'La matière change tout.',
            'content' => '<p>'.str_repeat('mot ', 450).'</p>',
            'is_published' => true, 'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product);
        $post->load('products.images');

        $this->assertSame($product->primaryImage()->sizedUrl('medium'), $post->coverUrl('medium'));
        $this->assertSame(3, $post->readingMinutes());

        $this->get('/journal')
            ->assertOk()
            ->assertSee('À la une')
            ->assertSee('3 min de lecture');

        $this->get('/journal/'.$post->slug)
            ->assertOk()
            ->assertSee('Partager sur WhatsApp')
            ->assertSee('Les pièces de cet article');
    }

    public function test_the_contact_page_shows_channels_and_social_networks(): void
    {
        SiteSetting::current()->update([
            'contact_whatsapp' => '+221 77 123 45 67',
            'contact_email' => 'bonjour@aissatou.test',
            'social_instagram' => 'https://instagram.com/aissatou',
            'social_tiktok' => null,
        ]);

        $this->get(route('shop.contact'))
            ->assertOk()
            ->assertSee('Parlons-en')
            ->assertSee('Le plus rapide')
            ->assertSee('mailto:bonjour@aissatou.test', false)
            ->assertSee('https://instagram.com/aissatou', false)
            ->assertDontSee('Tutoriels et tombés');
    }

    public function test_password_fields_can_be_revealed(): void
    {
        $this->get(route('login'))->assertSee('Afficher le mot de passe');
        $this->get(route('register'))->assertSee('Afficher le mot de passe');
    }
}

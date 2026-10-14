<?php

namespace Tests\Feature\Shop;

use App\Filament\Resources\Catalogue\CollectionResource\Pages\CreateCollection;
use App\Models\Admin;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CollectionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_collections_index_lists_active_collections_only(): void
    {
        Collection::create(['name' => 'Atelier Nocturne', 'slug' => 'atelier-nocturne', 'is_active' => true]);
        Collection::create(['name' => 'Archive', 'slug' => 'archive', 'is_active' => false]);

        $response = $this->get('/collections');

        $response->assertOk();
        $response->assertSee('Atelier Nocturne');
        $response->assertDontSee('Archive');
    }

    public function test_a_collection_page_shows_only_its_products(): void
    {
        $collection = Collection::create(['name' => 'Essentiels', 'slug' => 'essentiels', 'is_active' => true]);
        $member = Product::factory()->create(['is_active' => true]);
        $outsider = Product::factory()->create(['is_active' => true]);
        $collection->products()->attach($member);

        $response = $this->get('/collections/essentiels');

        $response->assertOk();
        $response->assertSee('Essentiels');
        $response->assertSee($member->name);
        $response->assertDontSee($outsider->name);
    }

    public function test_an_inactive_collection_returns_404(): void
    {
        Collection::create(['name' => 'Archive', 'slug' => 'archive', 'is_active' => false]);

        $this->get('/collections/archive')->assertNotFound();
    }

    public function test_filtering_the_catalogue_by_collection(): void
    {
        $collection = Collection::create(['name' => 'Saison', 'slug' => 'saison', 'is_active' => true]);
        $member = Product::factory()->create(['is_active' => true]);
        $outsider = Product::factory()->create(['is_active' => true]);
        $collection->products()->attach($member);

        $response = $this->get('/catalogue?collection_id='.$collection->id);

        $response->assertOk();
        $response->assertSee($member->name);
        $response->assertDontSee($outsider->name);
    }

    public function test_an_inactive_collection_filter_is_silently_ignored(): void
    {
        $collection = Collection::create(['name' => 'Archive', 'slug' => 'archive', 'is_active' => false]);
        $product = Product::factory()->create(['is_active' => true]);
        $collection->products()->attach($product);

        $this->get('/catalogue?collection_id='.$collection->id)
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_admin_can_create_a_collection_with_products(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $product = Product::factory()->create();

        Livewire::test(CreateCollection::class)
            ->fillForm([
                'name' => "L'art d'offrir",
                'description' => 'Prêts à offrir.',
                'products' => [$product->id],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $collection = Collection::where('name', "L'art d'offrir")->firstOrFail();
        $this->assertTrue($collection->products->contains($product));
        $this->get('/collections/'.$collection->slug)->assertSee($product->name);
    }
}

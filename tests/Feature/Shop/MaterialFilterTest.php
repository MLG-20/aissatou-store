<?php

namespace Tests\Feature\Shop;

use App\Filament\Resources\Catalogue\MaterialResource\Pages\CreateMaterial;
use App\Filament\Resources\Catalogue\ProductResource\Pages\CreateProduct;
use App\Models\Admin;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Material;
use App\Models\Catalogue\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaterialFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtering_by_material_returns_only_matching_products(): void
    {
        $soie = Material::create(['name' => 'Soie', 'slug' => 'soie']);
        $coton = Material::create(['name' => 'Coton', 'slug' => 'coton']);
        $silk = Product::factory()->create(['material_id' => $soie->id, 'is_active' => true]);
        $cotton = Product::factory()->create(['material_id' => $coton->id, 'is_active' => true]);
        $noMaterial = Product::factory()->create(['material_id' => null, 'is_active' => true]);

        $response = $this->get('/catalogue?material_id='.$soie->id);

        $response->assertOk();
        $response->assertSee($silk->name);
        $response->assertDontSee($cotton->name);
        $response->assertDontSee($noMaterial->name);
    }

    public function test_an_inactive_material_is_silently_ignored(): void
    {
        $soie = Material::create(['name' => 'Soie', 'slug' => 'soie', 'is_active' => false]);
        $product = Product::factory()->create(['material_id' => $soie->id, 'is_active' => true]);

        $response = $this->get('/catalogue?material_id='.$soie->id);

        $response->assertOk();
        $response->assertSee($product->name);
    }

    public function test_an_unknown_material_id_is_rejected(): void
    {
        $response = $this->get('/catalogue?material_id=999999');

        $response->assertSessionHasErrors('material_id');
    }

    public function test_admin_can_assign_a_material_to_a_product(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $material = Material::create(['name' => 'Modal', 'slug' => 'modal']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Hijab Modal Cassis',
                'slug' => 'hijab-modal-cassis',
                'category_id' => Category::factory()->create()->id,
                'price' => 27500,
                'material_id' => $material->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals($material->id, Product::firstOrFail()->material_id);
        $this->get('/catalogue?material_id='.$material->id)->assertSee('Hijab Modal Cassis');
    }

    public function test_admin_can_create_a_material_from_the_panel(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateMaterial::class)
            ->fillForm(['name' => 'Lin', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('materials', ['name' => 'Lin', 'slug' => 'lin']);
    }
}

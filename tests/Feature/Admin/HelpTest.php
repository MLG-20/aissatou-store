<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Help;
use App\Filament\Resources\Catalogue\ProductResource;
use App\Models\Admin;
use App\Support\AdminGuides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_team_finds_the_guide_on_the_help_page(): void
    {
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        $this->get(Help::getUrl())
            ->assertOk()
            ->assertSee('Que voulez-vous faire ?')
            ->assertSee('Ajouter un produit')
            ->assertSee('guide=ajouter-un-produit');
    }

    public function test_guests_cannot_open_the_help(): void
    {
        $this->get(Help::getUrl())->assertRedirect();
    }

    public function test_a_guide_is_hidden_without_access_to_its_screen(): void
    {
        $admin = Admin::factory()->create();
        $admin->syncRoles([]);

        $this->assertSame(['Bien démarrer'], array_keys(AdminGuides::for($admin)));
    }

    public function test_every_button_has_its_guide_steps(): void
    {
        $steps = file_get_contents(resource_path('js/admin-guides-data.js'));

        foreach (AdminGuides::all() as $guides) {
            foreach ($guides as $guide) {
                $this->assertStringContainsString("'{$guide['key']}': [", $steps);
            }
        }
    }

    /** Le guide repère les champs par leur libellé : le renommer le casserait. */
    public function test_the_labels_used_by_the_guide_still_exist(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->get(ProductResource::getUrl('create'))
            ->assertOk()
            ->assertSee('admin-guide', false)
            ->assertSeeInOrder(['Nom', 'Catégorie', 'Prix (FCFA)', 'Visible sur le site', 'Images', 'Déclinaisons', 'Stock initial']);
    }
}

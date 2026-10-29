<?php

namespace Tests\Feature\Shop;

use App\Enums\Content\LegalDocument;
use App\Filament\Resources\Content\LegalPageResource;
use App\Filament\Resources\Content\LegalPageResource\Pages\EditLegalPage;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Content\LegalPage;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pages légales : texte tenu en admin, identité légale reprise des réglages.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_four_pages_exist_as_drafts(): void
    {
        $this->assertSame(
            array_column(LegalDocument::cases(), 'value'),
            LegalPage::query()->orderBy('id')->get()->map(fn (LegalPage $page) => $page->document->value)->all()
        );

        foreach (LegalDocument::cases() as $document) {
            $this->get(route('shop.legal', $document))->assertNotFound();
        }
    }

    public function test_a_published_page_shows_its_sanitized_text_with_the_legal_identity(): void
    {
        SiteSetting::current()->update(['legal_name' => 'Aissatou Store SUARL', 'legal_ninea' => '']);
        LegalPage::for(LegalDocument::Notice)->update([
            'content' => '<h2>Éditeur</h2><p>{raison_sociale} — NINEA {ninea}</p><script>alert(1)</script>',
            'is_published' => true,
        ]);

        $this->get('/mentions-legales')
            ->assertOk()
            ->assertSee('Mentions légales')
            ->assertSee('Aissatou Store SUARL — NINEA [NINEA à compléter]', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_identity_values_are_escaped(): void
    {
        SiteSetting::current()->update(['legal_name' => '<b>Piège</b>']);
        LegalPage::for(LegalDocument::Notice)->update(['content' => '<p>{raison_sociale}</p>', 'is_published' => true]);

        $this->get('/mentions-legales')->assertSee('&lt;b&gt;Piège&lt;/b&gt;', false);
    }

    public function test_the_footer_links_published_pages_only(): void
    {
        LegalPage::for(LegalDocument::Terms)->update(['content' => '<p>x</p>', 'is_published' => true]);

        $this->get('/')
            ->assertSee(route('shop.legal', LegalDocument::Terms), false)
            ->assertDontSee(route('shop.legal', LegalDocument::Privacy), false);
    }

    public function test_the_owner_edits_a_page_and_it_is_logged(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $page = LegalPage::for(LegalDocument::Privacy);

        Livewire::test(EditLegalPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Politique de confidentialité', 'content' => '<p>Nouveau texte</p>', 'is_published' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($page->fresh()->is_published);
        $this->get('/confidentialite')->assertOk()->assertSee('Nouveau texte');
        $this->assertTrue(AdminActivity::query()->where('action', 'legal_page.updated')->exists());
    }

    public function test_pages_cannot_be_created_or_deleted(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->assertFalse(LegalPageResource::canCreate());
        $this->assertFalse(LegalPageResource::canDelete(LegalPage::first()));
    }
}

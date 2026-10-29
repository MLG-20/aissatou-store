<?php

namespace Tests\Feature\Shop;

use App\Enums\Content\CookieCategory;
use App\Enums\Content\LegalDocument;
use App\Filament\Resources\Content\CookieDeclarationResource\Pages\ManageCookieDeclarations;
use App\Models\Admin;
use App\Models\Content\CookieDeclaration;
use App\Models\Content\LegalPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Registre des cookies : la page « Politique cookies » le suit, sans texte
 * à recopier quand un outil arrive ou part.
 */
class CookieRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LegalPage::for(LegalDocument::Cookies)->update(['content' => '<p>Intro</p>', 'is_published' => true]);
    }

    public function test_the_registry_starts_with_the_cookies_the_site_really_sets(): void
    {
        $this->assertSame(
            [config('session.cookie'), 'XSRF-TOKEN', 'remember_web_…', 'aissatou_consent'],
            CookieDeclaration::query()->orderBy('position')->pluck('name')->all()
        );
        $this->assertFalse(CookieDeclaration::query()->get()->contains(fn (CookieDeclaration $cookie) => $cookie->category->requiresConsent()));
    }

    public function test_the_cookies_page_lists_active_cookies_by_category(): void
    {
        CookieDeclaration::create([
            'name' => '_ga', 'provider' => 'Google Analytics', 'category' => CookieCategory::Audience,
            'purpose' => 'Compte les visites.', 'duration' => '13 mois',
        ]);
        CookieDeclaration::create([
            'name' => '_retire', 'provider' => 'Ancien outil', 'category' => CookieCategory::Marketing,
            'purpose' => 'Plus utilisé.', 'duration' => '1 an', 'is_active' => false,
        ]);

        $this->get('/cookies')
            ->assertOk()
            ->assertSeeInOrder(['Nécessaires au fonctionnement', 'XSRF-TOKEN', 'Mesure d\'audience', '_ga', '13 mois'], false)
            ->assertDontSee('_retire');
    }

    public function test_other_legal_pages_do_not_show_the_registry(): void
    {
        LegalPage::for(LegalDocument::Terms)->update(['content' => '<p>CGV</p>', 'is_published' => true]);

        $this->get('/cgv')->assertOk()->assertDontSee('XSRF-TOKEN');
    }

    public function test_the_owner_declares_a_new_cookie_at_the_end_of_the_list(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ManageCookieDeclarations::class)
            ->callAction('create', [
                'name' => '_ga', 'provider' => 'Google Analytics', 'category' => CookieCategory::Audience->value,
                'duration' => '13 mois', 'purpose' => 'Compte les visites.', 'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('_ga', CookieDeclaration::query()->orderByDesc('position')->value('name'));
    }
}

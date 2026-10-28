<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accès direct à la boutique depuis le back-office (barre du haut).
 */
class ViewSiteButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_back_office_links_to_the_shop_in_a_new_tab(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('Voir le site', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('home'), '#').'"[^>]*target="_blank"#', $html);
    }
}

<?php

namespace Tests\Feature\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sur téléphone, se connecter ne doit pas exiger de fouiller le menu : icône
 * compte à côté du burger, et boutons en tête du tiroir.
 */
class MobileHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_reaches_the_login_from_the_mobile_header_and_menu_top(): void
    {
        $html = $this->get(route('shop.index'))->assertOk()->getContent();

        // Icône du bandeau mobile (sm:hidden) + icône bureau + bouton du tiroir
        // + lien du pied de page.
        $this->assertSame(4, substr_count($html, 'href="'.route('login').'"'));
        $this->assertStringContainsString('aria-label="Se connecter"', $html);
        $this->assertStringContainsString('Créer un compte', $html);
    }

    public function test_a_signed_in_customer_sees_her_account_instead(): void
    {
        $this->actingAs(User::factory()->create())->get(route('shop.index'))
            ->assertOk()
            ->assertSee('aria-label="Mon compte"', false)
            ->assertDontSee('Créer un compte');
    }
}

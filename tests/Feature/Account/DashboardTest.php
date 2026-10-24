<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_the_dashboard_greets_the_customer_with_quick_access_tiles(): void
    {
        $user = User::factory()->create([
            'name' => 'Aminata Diallo',
            'email' => 'aminata@example.com',
        ]);

        // La salutation suit l'heure : on se place en matinée.
        $this->travelTo(today()->setTime(10, 0));

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        // Accueil personnalisé (prénom) au lieu du « You're logged in » Breeze.
        $response->assertSeeText('Bonjour Aminata');
        $response->assertDontSee("You're logged in");
        // Tuiles d'accès rapide.
        $response->assertSee('Mes commandes');
        $response->assertSee('Mes adresses');
        $response->assertSee('Mes informations');
        // Résumé de profil.
        $response->assertSee('aminata@example.com');
    }
}

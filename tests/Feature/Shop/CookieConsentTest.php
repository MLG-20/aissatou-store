<?php

namespace Tests\Feature\Shop;

use App\Enums\Content\CookieCategory;
use App\Models\Content\CookieDeclaration;
use App\Support\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bandeau de consentement « prêt mais dormant » : invisible tant que le
 * registre ne contient que des cookies nécessaires.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    private function declareAnalytics(): void
    {
        CookieDeclaration::create([
            'name' => '_ga', 'provider' => 'Google Analytics', 'category' => CookieCategory::Audience,
            'purpose' => 'Compte les visites.', 'duration' => '13 mois',
        ]);
    }

    private function consent(array $granted, array $asked): string
    {
        return json_encode(['granted' => $granted, 'asked' => $asked]);
    }

    public function test_the_banner_sleeps_while_only_necessary_cookies_are_declared(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Vos cookies, votre choix')
            ->assertDontSee('Gérer les cookies');
    }

    public function test_the_banner_wakes_up_when_a_consent_cookie_is_declared(): void
    {
        $this->declareAnalytics();

        $this->get('/')
            ->assertSee('Vos cookies, votre choix')
            ->assertSee('Gérer les cookies');
        $this->assertSame(['mesure-audience'], CookieConsent::categories());
    }

    public function test_a_retired_tool_puts_the_banner_back_to_sleep(): void
    {
        $this->declareAnalytics();
        CookieDeclaration::query()->where('name', '_ga')->update(['is_active' => false]);
        // update() de masse ne déclenche pas les événements : on passe par le modèle.
        CookieDeclaration::query()->where('name', '_ga')->first()->save();

        $this->assertSame([], CookieConsent::categories());
    }

    public function test_the_visitor_is_asked_again_only_for_a_new_category(): void
    {
        $this->declareAnalytics();
        $request = fn (string $cookie) => request()->duplicate(cookies: [CookieConsent::COOKIE => $cookie]);

        $this->assertFalse(CookieConsent::shouldAsk($request($this->consent([], ['mesure-audience']))));

        CookieDeclaration::create([
            'name' => '_fbp', 'provider' => 'Meta', 'category' => CookieCategory::Marketing,
            'purpose' => 'Mesure des publicités.', 'duration' => '3 mois',
        ]);

        $this->assertTrue(CookieConsent::shouldAsk($request($this->consent([], ['mesure-audience']))));
    }

    public function test_the_choice_cookie_is_read_unencrypted_and_cleaned(): void
    {
        $this->declareAnalytics();
        $this->get('/')->assertSee('\\u0022open\\u0022:true', false);

        $this->withUnencryptedCookie(CookieConsent::COOKIE, $this->consent(['mesure-audience', 'necessaire', 'pirate'], ['mesure-audience']))
            ->get('/')
            // Déjà interrogé : le bandeau reste en place (lien « Gérer ») mais fermé.
            ->assertSee('\\u0022granted\\u0022:[\\u0022mesure-audience\\u0022],\\u0022open\\u0022:false', false);

        $request = request()->duplicate(cookies: [CookieConsent::COOKIE => $this->consent(['mesure-audience', 'necessaire', 'pirate'], [])]);
        $this->assertSame(['mesure-audience'], CookieConsent::granted($request));
        $this->assertSame([], CookieConsent::granted(request()->duplicate(cookies: [CookieConsent::COOKIE => '{pas du json'])));
    }

    public function test_consent_gated_content_stays_inert_until_accepted(): void
    {
        $markup = '<x-consent category="mesure-audience"><script>window.ga = 1</script></x-consent>';

        $this->assertStringContainsString(
            '<template data-consent="mesure-audience"><script>window.ga = 1</script></template>',
            (string) $this->blade($markup)
        );

        $this->app->instance('request', request()->duplicate(cookies: [CookieConsent::COOKIE => $this->consent(['mesure-audience'], ['mesure-audience'])]));

        $rendered = (string) $this->blade($markup);
        $this->assertStringContainsString('<script>window.ga = 1</script>', $rendered);
        $this->assertStringNotContainsString('<template', $rendered);
    }
}

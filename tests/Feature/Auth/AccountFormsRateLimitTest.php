<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sans limite, une même adresse IP pouvait créer des comptes en masse, faire
 * envoyer des e-mails à n'importe qui et essayer des jetons de réinitialisation.
 */
class AccountFormsRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Trop de tentatives depuis votre connexion. Merci de patienter quelques instants avant de réessayer.';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function register(string $email, string $ip = '203.0.113.10'): TestResponse
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/register')->post('/register', [
            'name' => 'Test',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // L'inscription connecte le compte : on redevient visiteur pour la suivante.
        Auth::guard('web')->logout();

        return $response;
    }

    public function test_accounts_created_in_a_row_from_one_ip_are_stopped(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->register("essai{$i}@example.com");
        }

        $this->assertSame(5, User::query()->count());
    }

    public function test_the_visitor_is_told_why_in_french_and_keeps_the_typed_email(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->register("essai{$i}@example.com");
        }

        $this->register('patiente@example.com')
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => self::MESSAGE])
            ->assertSessionHasInput('email', 'patiente@example.com')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_another_ip_is_not_blocked(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->register("essai{$i}@example.com");
        }

        $this->register('voisine@example.com', '203.0.113.20');

        $this->assertDatabaseHas('users', ['email' => 'voisine@example.com']);
    }

    public function test_the_hourly_limit_applies_after_the_minute_limit_has_passed(): void
    {
        for ($i = 0; $i < 40; $i++) {
            // Une minute et une seconde entre deux lots de 5 : seule l'heure peut bloquer.
            if ($i > 0 && $i % 5 === 0) {
                $this->travel(61)->seconds();
            }

            $this->register("essai{$i}@example.com");
        }

        $this->assertSame(30, User::query()->count());
    }

    public function test_reset_emails_requested_in_a_row_from_one_ip_are_stopped(): void
    {
        $users = User::factory()->count(8)->create();

        foreach ($users as $user) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        }

        Notification::assertSentTimes(ResetPassword::class, 5);

        $this->from('/forgot-password')->post('/forgot-password', ['email' => $users->first()->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => self::MESSAGE]);
    }

    public function test_guessing_a_reset_token_is_stopped_even_with_the_right_token_afterwards(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $attempt = fn (string $tried) => $this->from('/reset-password/x')->post('/reset-password', [
            'token' => $tried,
            'email' => $user->email,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $attempt("mauvais-jeton-{$i}");
        }

        $attempt($token)->assertSessionHasErrors(['email' => self::MESSAGE]);

        $this->assertFalse(Hash::check('nouveau-mot-de-passe', $user->fresh()->password));
    }

    public function test_each_form_has_its_own_counter(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        }

        $this->register('nouvelle@example.com');

        $this->assertDatabaseHas('users', ['email' => 'nouvelle@example.com']);
    }

    public function test_a_normal_visit_is_not_limited_when_only_displaying_the_pages(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/register')->assertOk();
        }
    }
}

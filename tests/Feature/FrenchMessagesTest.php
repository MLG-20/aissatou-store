<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Sans dossier lang/fr, les clientes voyaient des messages en anglais, ou des
 * clés brutes comme « auth.failed » quand le français était seul configuré.
 */
class FrenchMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Comme en production : français seul, aucun repli sur l'anglais.
        $this->app->setLocale('fr');
        $this->app['translator']->setFallback('fr');
    }

    public function test_every_framework_message_exists_in_french(): void
    {
        $english = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');

        foreach (['auth', 'passwords', 'pagination', 'validation'] as $file) {
            $path = lang_path("fr/{$file}.php");
            $this->assertFileExists($path);

            // « custom » et « attributes » ne sont que des exemples vides dans le fichier anglais.
            $expected = Arr::except(require "{$english}/{$file}.php", ['custom', 'attributes']);

            $missing = array_diff_key(Arr::dot($expected), Arr::dot(require $path));

            $this->assertSame([], array_keys($missing), "Manquant dans lang/fr/{$file}.php");
        }
    }

    public function test_a_wrong_password_is_explained_in_french(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'mauvais'])
            ->assertSessionHasErrors(['email' => 'Ces identifiants ne correspondent pas à nos enregistrements.']);
    }

    public function test_a_form_error_names_the_field_in_french(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Test',
            'email' => '',
            'password' => 'court',
            'password_confirmation' => 'autre',
        ])->assertSessionHasErrors([
            'email' => 'Le champ adresse e-mail est obligatoire.',
            'password' => 'La confirmation du champ mot de passe ne correspond pas.',
        ]);
    }

    public function test_the_password_reset_email_is_in_french(): void
    {
        $user = User::factory()->create();

        $mail = (new QueuedResetPassword('jeton'))->toMail($user);
        $text = (string) $mail->render();

        $this->assertSame('Choisissez un nouveau mot de passe', $mail->subject);
        $this->assertStringContainsString('Bonjour', $text);
        $this->assertStringContainsString('Ce lien expirera dans 60 minutes.', $text);
        $this->assertStringNotContainsString('Reset', $text);
        $this->assertStringNotContainsString('Regards', $text);
        $this->assertStringNotContainsString('trouble', $text);
    }

    public function test_the_verification_email_is_in_french(): void
    {
        $user = User::factory()->unverified()->create();

        $mail = (new VerifyEmail)->toMail($user);
        $text = (string) $mail->render();

        $this->assertSame('Vérifiez votre adresse e-mail', $mail->subject);
        $this->assertStringContainsString('Vérifier mon adresse e-mail', $text);
        $this->assertStringNotContainsString('Verify', $text);
    }
}

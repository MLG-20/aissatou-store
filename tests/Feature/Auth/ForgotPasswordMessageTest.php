<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * La réponse ne doit pas révéler si une adresse a un compte dans la boutique.
 */
class ForgotPasswordMessageTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Si un compte existe avec cette adresse, un lien pour choisir un nouveau mot de passe vient de lui être envoyé.';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function assertSameAnswerFor(string $email): void
    {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status', self::MESSAGE)
            ->assertSessionHasNoErrors();
    }

    public function test_an_unknown_address_gets_the_same_answer_and_no_email(): void
    {
        $this->assertSameAnswerFor('inconnue@example.com');

        Notification::assertNothingSent();
    }

    public function test_a_known_address_gets_the_same_answer_and_the_email(): void
    {
        $user = User::factory()->create();

        $this->assertSameAnswerFor($user->email);

        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    public function test_a_second_request_right_after_gets_the_same_answer_without_a_second_email(): void
    {
        $user = User::factory()->create();

        $this->assertSameAnswerFor($user->email);
        $this->assertSameAnswerFor($user->email);

        Notification::assertSentToTimes($user, QueuedResetPassword::class, 1);
    }

    public function test_an_invalid_address_is_still_refused(): void
    {
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'pas-une-adresse'])
            ->assertSessionHasErrors('email');
    }
}

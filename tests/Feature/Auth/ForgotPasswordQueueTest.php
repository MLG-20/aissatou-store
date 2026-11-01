<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Envoyé pendant la requête, l'e-mail rendait la réponse plus lente pour une
 * adresse connue : le temps de réponse révélait qu'elle avait un compte.
 */
class ForgotPasswordQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_waits_in_the_queue_instead_of_being_sent_during_the_request(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof ResetPassword && $job->shouldBeEncrypted);
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());
    }

    public function test_once_processed_the_email_carries_the_reset_link(): void
    {
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $messages = app('mailer')->getSymfonyTransport()->messages();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('reset-password/', $messages->first()->toString());
    }
}

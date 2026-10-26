<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = [
        'name' => 'Mariama',
        'email' => 'mariama@example.com',
        'message' => 'Avez-vous ce voile en bleu nuit ?',
    ];

    public function test_a_message_is_saved(): void
    {
        $this->post(route('shop.contact.store'), self::MESSAGE)
            ->assertRedirect()
            ->assertSessionHas('status', 'message-sent');

        $this->assertDatabaseHas('contact_messages', ['email' => 'mariama@example.com']);
    }

    public function test_a_bot_filling_the_honeypot_gets_the_same_answer_but_nothing_is_saved(): void
    {
        $this->post(route('shop.contact.store'), self::MESSAGE + ['website' => 'https://spam.example'])
            ->assertRedirect()
            ->assertSessionHas('status', 'message-sent');

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contact_submissions_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('shop.contact.store'), self::MESSAGE)->assertRedirect();
        }

        $this->post(route('shop.contact.store'), self::MESSAGE)->assertTooManyRequests();
        $this->assertDatabaseCount('contact_messages', 5);
    }

    public function test_the_contact_form_contains_the_honeypot(): void
    {
        $this->get(route('shop.contact'))->assertSee('name="website"', false);
    }
}

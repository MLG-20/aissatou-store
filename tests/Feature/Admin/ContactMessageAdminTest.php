<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Content\ContactMessageResource\Pages\ListContactMessages;
use App\Filament\Resources\Content\ContactMessageResource\Pages\ViewContactMessage;
use App\Models\Admin;
use App\Models\Contact\ContactMessage;
use App\Models\Content\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactMessageAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_and_read_messages(): void
    {
        ContactMessage::create([
            'name' => 'Awa', 'email' => 'awa@example.com', 'message' => 'Bonjour, une question.',
        ]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ListContactMessages::class)
            ->assertSee('Awa');

        $message = ContactMessage::firstOrFail();

        Livewire::test(ViewContactMessage::class, ['record' => $message->getRouteKey()])
            ->assertSee('Bonjour, une question.');
    }

    public function test_admin_can_mark_a_message_as_read(): void
    {
        $message = ContactMessage::create([
            'name' => 'Awa', 'email' => 'awa@example.com', 'message' => 'Bonjour.',
        ]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ListContactMessages::class)
            ->callTableAction('toggleRead', $message->getRouteKey())
            ->assertHasNoActionErrors();

        $this->assertTrue($message->fresh()->is_read);
    }

    public function test_contact_page_links_whatsapp_and_socials(): void
    {
        SiteSetting::current()->update([
            'contact_whatsapp' => '+221 77 000 00 00',
            'social_instagram' => 'https://instagram.com/aissatoustore',
        ]);

        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertSee('https://wa.me/221770000000', false);
        $response->assertSee('https://instagram.com/aissatoustore');
    }
}

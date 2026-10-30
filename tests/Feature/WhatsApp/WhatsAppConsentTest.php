<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\Order\WhatsAppMessageStatus;
use App\Enums\Order\WhatsAppTemplate;
use App\Filament\Resources\Order\OrderResource\Pages\ViewOrder;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Accord du client au checkout et bloc WhatsApp de la fiche commande
 * (étape E, docs/files/08-whatsapp-cloud-api.md §7–8).
 */
class WhatsAppConsentTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    private User $user;

    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.enabled' => true,
            'whatsapp.phone_number_id' => '1234567890',
            'whatsapp.access_token' => 'jeton-de-test',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        $this->user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->address = Address::factory()->for($this->user)->create(['is_default' => true, 'phone' => '77 123 45 67']);
    }

    public function test_the_checkout_offers_an_unchecked_whatsapp_box(): void
    {
        $this->actingAs($this->user)->get(route('shop.checkout'))
            ->assertOk()
            ->assertSee('Recevoir le suivi de ma commande sur WhatsApp au')
            ->assertSee('77 123 45 67')
            ->assertSee('name="whatsapp_opt_in" value="1"  class', false);
    }

    public function test_no_box_while_whatsapp_is_switched_off(): void
    {
        config(['whatsapp.enabled' => false]);

        $this->actingAs($this->user)->get(route('shop.checkout'))
            ->assertOk()
            ->assertDontSee('whatsapp_opt_in');
    }

    public function test_ticking_the_box_records_the_agreement_and_sends_the_confirmation(): void
    {
        $this->actingAs($this->user)->post(route('shop.checkout.store'), [
            'address_id' => $this->address->id,
            'provider' => 'cod',
            'whatsapp_opt_in' => '1',
        ]);

        $order = Order::sole();
        $this->assertTrue($order->whatsapp_opt_in);
        $this->assertNotNull($order->whatsapp_opt_in_at);
        $this->assertSame(WhatsAppMessageStatus::Sent, $order->whatsappMessages()->sole()->status);
    }

    public function test_leaving_the_box_unticked_means_email_only(): void
    {
        $this->actingAs($this->user)->post(route('shop.checkout.store'), [
            'address_id' => $this->address->id,
            'provider' => 'cod',
        ]);

        $this->assertFalse(Order::sole()->whatsapp_opt_in);
        Http::assertNothingSent();
    }

    public function test_the_order_sheet_shows_the_whatsapp_follow_up(): void
    {
        $this->actingAs($this->user)->post(route('shop.checkout.store'), [
            'address_id' => $this->address->id,
            'provider' => 'cod',
            'whatsapp_opt_in' => '1',
        ]);
        $order = Order::sole();
        $order->whatsappMessages()->sole()->update(['status' => WhatsAppMessageStatus::Read]);
        $order->whatsappMessages()->create([
            'to' => '+221771234567',
            'template' => WhatsAppTemplate::OrderShipped,
            'status' => WhatsAppMessageStatus::Failed,
            'error' => 'Refus de Meta 131026 : Message undeliverable',
        ]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('Accord du client')
            ->assertSee('Oui, donné le')
            ->assertSee('Commande confirmée')
            ->assertSee('Lu')
            ->assertSee('Commande expédiée')
            ->assertSee('Échec')
            ->assertSee('Refus de Meta 131026');
    }
}

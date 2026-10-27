<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\Order\WhatsAppMessageStatus;
use App\Jobs\SendOrderWhatsApp;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Confirmation de commande sur WhatsApp (étape B,
 * docs/files/08-whatsapp-cloud-api.md) : envoyée seulement avec l'accord du
 * client, journalisée, et sans jamais faire échouer la commande.
 */
class OrderWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://graph.facebook.com/v23.0/1234567890/messages';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.enabled' => true,
            'whatsapp.api_version' => 'v23.0',
            'whatsapp.phone_number_id' => '1234567890',
            'whatsapp.access_token' => 'jeton-de-test',
        ]);
    }

    private function placeOrder(bool $optIn = true, string $phone = '77 123 45 67'): Order
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 18000]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 5]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        $address = Address::factory()->for($user)->create(['recipient_name' => 'Aminata Diop', 'phone' => $phone, 'city' => 'Ville sans zone']);

        return app(OrderService::class)->createFromCart($user, $cart, $address, 'cod', whatsappOptIn: $optIn);
    }

    public function test_the_confirmation_template_is_sent_when_the_customer_agreed(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        $order = $this->placeOrder();

        Http::assertSent(function (Request $request) use ($order) {
            $template = $request['template'];

            return $request->url() === self::ENDPOINT
                && $request->hasHeader('Authorization', 'Bearer jeton-de-test')
                && $request['to'] === '221771234567'
                && $template['name'] === 'commande_confirmee'
                && $template['language']['code'] === 'fr'
                && array_column($template['components'][0]['parameters'], 'text') === ['Aminata', $order->number, '36 000 FCFA']
                && $template['components'][1]['parameters'][0]['text'] === (string) $order->id;
        });

        $message = $order->whatsappMessages()->sole();
        $this->assertSame(WhatsAppMessageStatus::Sent, $message->status);
        $this->assertSame('wamid.ABC', $message->wamid);
        $this->assertSame('+221771234567', $message->to);
        $this->assertTrue($order->whatsapp_opt_in);
        $this->assertNotNull($order->whatsapp_opt_in_at);
    }

    public function test_nothing_is_sent_without_the_customer_agreement(): void
    {
        Http::fake();

        $order = $this->placeOrder(optIn: false);

        Http::assertNothingSent();
        $this->assertFalse($order->whatsapp_opt_in);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_nothing_is_sent_while_whatsapp_is_switched_off(): void
    {
        config(['whatsapp.enabled' => false]);
        Http::fake();

        $this->placeOrder();

        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_an_unusable_number_is_logged_as_failed_without_calling_meta(): void
    {
        Http::fake();

        $order = $this->placeOrder(phone: '77313166');

        Http::assertNothingSent();
        $message = $order->whatsappMessages()->sole();
        $this->assertSame(WhatsAppMessageStatus::Failed, $message->status);
        $this->assertStringContainsString('invalide', $message->error);
    }

    public function test_a_meta_refusal_is_logged_and_the_order_still_exists(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => [
            'code' => 131026, 'message' => 'Message undeliverable',
        ]], 400)]);

        $order = $this->placeOrder();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $message = $order->whatsappMessages()->sole();
        $this->assertSame(WhatsAppMessageStatus::Failed, $message->status);
        $this->assertStringContainsString('131026', $message->error);
    }

    public function test_a_temporary_outage_is_retried_not_marked_failed(): void
    {
        Http::fake([self::ENDPOINT => fn () => throw new ConnectionException('timeout')]);

        $order = $this->placeOrder();

        $message = $order->whatsappMessages()->sole();
        $this->assertSame(WhatsAppMessageStatus::Queued, $message->status);
        $this->assertSame('Meta injoignable', $message->error);
    }

    public function test_a_replayed_job_never_sends_twice(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);
        $order = $this->placeOrder();

        dispatch(new SendOrderWhatsApp($order->whatsappMessages()->sole()));

        Http::assertSentCount(1);
    }
}

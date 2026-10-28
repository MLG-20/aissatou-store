<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\Order\OrderStatus;
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
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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

    private function placeOrder(bool $optIn = true, string $phone = '77 123 45 67', string $provider = 'cod'): Order
    {
        config(['payment.paytech.api_key' => 'cle', 'payment.paytech.api_secret' => 'secret']);
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 18000]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 5]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        $address = Address::factory()->for($user)->create(['recipient_name' => 'Aminata Diop', 'phone' => $phone, 'city' => 'Ville sans zone']);

        return app(OrderService::class)->createFromCart($user, $cart, $address, $provider, whatsappOptIn: $optIn);
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

    public function test_each_status_change_sends_its_own_template(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['messages' => [['id' => 'wamid.1']]])
            ->push(['messages' => [['id' => 'wamid.2']]])
            ->push(['messages' => [['id' => 'wamid.3']]])]);
        $order = $this->placeOrder();
        $orders = app(OrderService::class);

        // « En préparation » : rien, trop proche de la confirmation.
        $order = $orders->changeStatus($order, OrderStatus::Preparing);
        $order = $orders->changeStatus($order, OrderStatus::Shipped);
        $orders->changeStatus($order, OrderStatus::Delivered);

        $this->assertSame(
            ['commande_confirmee', 'commande_expediee', 'commande_livree'],
            $order->whatsappMessages()->get()->map(fn ($message) => $message->template->value)->all()
        );
        Http::assertSent(fn (Request $request) => $request['template']['name'] === 'commande_livree'
            && array_column($request['template']['components'][0]['parameters'], 'text') === ['Aminata', $order->number]);
    }

    public function test_a_cancellation_is_announced(): void
    {
        Http::fake([self::ENDPOINT => fn () => Http::response(['messages' => [['id' => 'wamid.'.Str::random(8)]]])]);
        $order = $this->placeOrder();

        app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled);

        $this->assertSame('commande_annulee', $order->whatsappMessages()->latest('id')->first()->template->value);
    }

    public function test_a_confirmed_payment_is_announced_once(): void
    {
        Http::fake([self::ENDPOINT => fn () => Http::response(['messages' => [['id' => 'wamid.'.Str::random(8)]]])]);
        $order = $this->placeOrder();
        $payments = app(PaymentService::class);

        $payments->confirm($order->payment);
        // Confirmation rejouée (IPN reçue deux fois) : pas de second message.
        $payments->confirm($order->payment->fresh());

        $this->assertSame(1, $order->whatsappMessages()->where('template', 'paiement_confirme')->count());
        Http::assertSent(fn (Request $request) => $request['template']['name'] === 'paiement_confirme'
            && array_column($request['template']['components'][0]['parameters'], 'text') === ['Aminata', $order->number, '36 000 FCFA']);
    }

    public function test_an_online_payment_is_announced_by_a_single_message(): void
    {
        Http::fake([self::ENDPOINT => fn () => Http::response(['messages' => [['id' => 'wamid.'.Str::random(8)]]])]);
        $order = $this->placeOrder(provider: 'paytech');

        // Rien tant que le paiement n'a pas abouti.
        Http::assertNothingSent();

        app(PaymentService::class)->confirm($order->payment);

        $this->assertSame(['paiement_confirme'], $order->whatsappMessages()->get()->map(fn ($message) => $message->template->value)->all());
    }

    public function test_an_abandoned_online_payment_is_cancelled_silently(): void
    {
        Http::fake();
        $order = $this->placeOrder(provider: 'paytech');

        app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled);

        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_a_paid_online_order_cancelled_by_the_shop_is_announced(): void
    {
        Http::fake([self::ENDPOINT => fn () => Http::response(['messages' => [['id' => 'wamid.'.Str::random(8)]]])]);
        $order = $this->placeOrder(provider: 'paytech');
        app(PaymentService::class)->confirm($order->payment);

        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Cancelled);

        $this->assertSame(
            ['paiement_confirme', 'commande_annulee'],
            $order->whatsappMessages()->get()->map(fn ($message) => $message->template->value)->all()
        );
    }

    public function test_a_replayed_job_never_sends_twice(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);
        $order = $this->placeOrder();

        dispatch(new SendOrderWhatsApp($order->whatsappMessages()->sole()));

        Http::assertSentCount(1);
    }
}

<?php

namespace Tests\Feature\Payment;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Mail\NewOrderForAdmin;
use App\Mail\OrderPlaced;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\Order\PaymentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Checklist phase 5 (docs/files/05-paiements-electroniques.md, §7) : chaque
 * case a au moins un test ici. Aucun appel réseau réel (preventStrayRequests).
 */
class PayTechTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-api-key';

    private const SECRET = 'test-api-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.paytech.api_key' => self::KEY,
            'payment.paytech.api_secret' => self::SECRET,
            'payment.paytech.env' => 'test',
            'payment.paytech.ipn_url' => null,
        ]);
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function fakePayTech(string $state = 'running'): void
    {
        Http::fake([
            'paytech.sn/api/payment/request-payment' => Http::sequence()
                ->push(['success' => 1, 'token' => 'tok-1', 'redirect_url' => 'https://paytech.sn/payment/checkout/tok-1'])
                ->push(['success' => 1, 'token' => 'tok-2', 'redirect_url' => 'https://paytech.sn/payment/checkout/tok-2']),
            'paytech.sn/api/payment/get-status*' => Http::response(['success' => 1, 'payment' => ['state' => $state]]),
        ]);
    }

    /** @return array{0: User, 1: Order, 2: ProductVariant} */
    private function placeOrder(): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 12000]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 4, 'price_override' => null]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        $address = Address::factory()->for($user)->create();

        $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'paytech',
        ])->assertRedirect('https://paytech.sn/payment/checkout/tok-1');

        return [$user, Order::firstOrFail(), $variant];
    }

    private function ipn(Order $order, array $overrides = [], int $attempt = 1): array
    {
        $ipn = [
            'type_event' => 'sale_complete',
            'ref_command' => $order->number.'-'.$attempt,
            'item_price' => (string) (int) $order->total,
            'final_item_price' => '125',
            'payment_method' => 'Wave',
            'client_phone' => '221771234567',
            'token' => 'tok-1',
            'env' => 'test',
        ];
        $ipn = $overrides + $ipn;
        $ipn['hmac_compute'] ??= hash_hmac('sha256', $ipn['final_item_price'].'|'.$ipn['ref_command'].'|'.self::KEY, self::SECRET);

        return $ipn;
    }

    public function test_checkout_sends_the_server_total_and_redirects_to_paytech(): void
    {
        $this->fakePayTech();

        [, $order] = $this->placeOrder();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/payment/request-payment')
            && $request['item_price'] === 24000
            && $request['ref_command'] === $order->number.'-1'
            && $request['env'] === 'test'
            && $request->hasHeader('API_KEY', self::KEY));

        $payment = $order->payment;
        $this->assertEquals(PaymentStatus::Processing, $payment->status);
        $this->assertEquals('tok-1', $payment->token);
        $this->assertTrue($payment->expires_at->isFuture());
        $this->assertTrue($payment->events->contains('type', PaymentEventType::Initiated));
        // Pas d'alerte boutique tant que rien n'est payé ; le client, lui, a son récapitulatif.
        Mail::assertNotQueued(NewOrderForAdmin::class);
        Mail::assertQueued(OrderPlaced::class);
    }

    public function test_an_unreachable_provider_never_blocks_the_customer(): void
    {
        Http::fake(['paytech.sn/*' => fn () => throw new ConnectionException('timeout')]);
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'paytech',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('shop.checkout.payment', $order->number));
        $response->assertSessionHasErrors('payment');
        $this->assertEquals(PaymentStatus::Pending, $order->payment->status);
        $this->assertTrue($order->payment->events->contains('type', PaymentEventType::InitiationFailed));
    }

    public function test_a_forged_success_return_url_does_not_mark_the_order_paid(): void
    {
        $this->fakePayTech('running');
        [$user, $order] = $this->placeOrder();

        $this->actingAs($user)->get(route('shop.checkout.payment', $order->number).'?status=success&paid=1')
            ->assertOk()
            ->assertSee('Paiement en cours de vérification');
        $this->actingAs($user)->get(route('shop.checkout.success', $order->number))
            ->assertRedirect(route('shop.checkout.payment', $order->number));

        $this->assertEquals(PaymentStatus::Processing, $order->payment->fresh()->status);
    }

    public function test_a_signed_ipn_confirms_the_payment_and_alerts_the_shop_once(): void
    {
        $this->fakePayTech();
        [$user, $order] = $this->placeOrder();

        $this->post(route('payment.paytech.ipn'), $this->ipn($order))->assertOk();
        // Doublon : PayTech renvoie régulièrement le même IPN.
        $this->post(route('payment.paytech.ipn'), $this->ipn($order))->assertOk();

        $payment = $order->payment->fresh();
        $this->assertEquals(PaymentStatus::Confirmed, $payment->status);
        $this->assertEquals(PaymentStatus::Confirmed, $order->fresh()->payment_status);
        $this->assertEquals('Wave', $payment->method);
        $this->assertEquals($order->number.'-1', $payment->reference);
        $this->assertEquals(1, $payment->events()->where('type', PaymentEventType::Confirmed)->count());
        Mail::assertQueued(NewOrderForAdmin::class, 1);

        // Le journal ne garde que les 2 derniers chiffres du payeur.
        $this->assertEquals('…67', $payment->events()->where('type', PaymentEventType::Notification)->first()->payload['client_phone']);

        $this->actingAs($user)->get(route('shop.checkout.payment', $order->number))
            ->assertRedirect(route('shop.checkout.success', $order->number));
    }

    public function test_an_ipn_with_an_invalid_signature_is_rejected_and_logged_as_suspicious(): void
    {
        $this->fakePayTech();
        [, $order] = $this->placeOrder();

        $this->post(route('payment.paytech.ipn'), $this->ipn($order, ['hmac_compute' => str_repeat('0', 64)]))
            ->assertForbidden();

        $this->assertEquals(PaymentStatus::Processing, $order->payment->fresh()->status);
        $suspect = PaymentEvent::where('type', PaymentEventType::SuspiciousNotification)->firstOrFail();
        $this->assertEquals($order->payment->id, $suspect->payment_id);
        $this->assertEquals('signature invalide', $suspect->payload['motif']);
        Mail::assertNotQueued(NewOrderForAdmin::class);
    }

    public function test_an_ipn_for_another_amount_is_rejected(): void
    {
        $this->fakePayTech();
        [, $order] = $this->placeOrder();

        $this->post(route('payment.paytech.ipn'), $this->ipn($order, ['item_price' => '100']))
            ->assertStatus(422);

        $this->assertEquals(PaymentStatus::Processing, $order->payment->fresh()->status);
        $this->assertEquals('montant différent', PaymentEvent::where('type', PaymentEventType::SuspiciousNotification)->first()->payload['motif']);
    }

    public function test_an_ipn_for_an_unknown_order_is_logged_without_a_payment(): void
    {
        $this->post(route('payment.paytech.ipn'), [
            'type_event' => 'sale_complete',
            'ref_command' => 'AS-2026-ZZZZZZ-1',
            'item_price' => '1000',
            'hmac_compute' => hash_hmac('sha256', '1000|AS-2026-ZZZZZZ-1|'.self::KEY, self::SECRET),
        ])->assertNotFound();

        $this->assertNull(PaymentEvent::firstOrFail()->payment_id);
    }

    public function test_a_sha256_only_ipn_is_only_trusted_after_active_verification(): void
    {
        $this->fakePayTech('running');
        [, $order] = $this->placeOrder();
        $legacy = [
            'type_event' => 'sale_complete',
            'ref_command' => $order->number.'-1',
            'item_price' => '24000',
            'api_key_sha256' => hash('sha256', self::KEY),
            'api_secret_sha256' => hash('sha256', self::SECRET),
        ];

        $this->post(route('payment.paytech.ipn'), $legacy)->assertOk();

        // PayTech dit encore « running » : pas de confirmation sur la seule empreinte.
        $this->assertEquals(PaymentStatus::Processing, $order->payment->fresh()->status);
    }

    public function test_a_cancelled_payment_can_be_retried_with_a_new_reference(): void
    {
        $this->fakePayTech();
        [$user, $order] = $this->placeOrder();

        $this->post(route('payment.paytech.ipn'), $this->ipn($order, ['type_event' => 'sale_canceled']))->assertOk();
        $this->assertEquals(PaymentStatus::Failed, $order->payment->fresh()->status);

        $this->actingAs($user)->get(route('shop.checkout.payment', $order->number))
            ->assertOk()
            ->assertSee('Le paiement n\'a pas abouti', false)
            ->assertSee('Réessayer le paiement');

        $this->actingAs($user)->post(route('shop.checkout.payment.retry', $order->number))
            ->assertRedirect('https://paytech.sn/payment/checkout/tok-2');

        Http::assertSent(fn (Request $request) => ($request['ref_command'] ?? null) === $order->number.'-2');

        // Le paiement de la seconde tentative est reconnu.
        $this->post(route('payment.paytech.ipn'), $this->ipn($order, ['token' => 'tok-2'], attempt: 2))->assertOk();
        $this->assertEquals(PaymentStatus::Confirmed, $order->payment->fresh()->status);
    }

    public function test_a_late_cancellation_of_a_previous_attempt_does_not_fail_the_current_one(): void
    {
        $this->fakePayTech();
        [$user, $order] = $this->placeOrder();
        $this->post(route('payment.paytech.ipn'), $this->ipn($order, ['type_event' => 'sale_canceled']))->assertOk();
        $this->actingAs($user)->post(route('shop.checkout.payment.retry', $order->number));
        $this->assertEquals('tok-2', $order->payment->fresh()->token);

        // Annulation authentique mais de la tentative 1 (jeton tok-1), rejouée.
        $this->post(route('payment.paytech.ipn'), $this->ipn($order, ['type_event' => 'sale_canceled']))->assertOk();

        $this->assertEquals(PaymentStatus::Processing, $order->payment->fresh()->status);
    }

    public function test_a_redirect_outside_paytech_is_refused(): void
    {
        Http::fake([
            'paytech.sn/api/payment/request-payment' => Http::response(['success' => 1, 'token' => 'tok-1', 'redirect_url' => 'https://evil.example/phish']),
        ]);
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'paytech',
        ]);

        $response->assertRedirect(route('shop.checkout.payment', Order::firstOrFail()->number));
        $response->assertSessionHasErrors('payment');
    }

    public function test_someone_elses_payment_page_is_forbidden(): void
    {
        $this->fakePayTech();
        [, $order] = $this->placeOrder();

        $this->actingAs(User::factory()->create())
            ->get(route('shop.checkout.payment', $order->number))->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post(route('shop.checkout.payment.retry', $order->number))->assertForbidden();
    }

    public function test_unpaid_orders_expire_and_their_stock_is_released(): void
    {
        $this->fakePayTech('running');
        [$user, $order, $variant] = $this->placeOrder();
        $this->assertEquals(2, $variant->fresh()->stock);

        $this->travel(31)->minutes();
        $this->artisan('payments:expire')->assertSuccessful();

        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Failed, $order->payment->fresh()->status);
        $this->assertEquals(4, $variant->fresh()->stock);
        // Une seule ligne au journal pour l'expiration, pas « échoué » en plus.
        $events = $order->payment->fresh()->events;
        $this->assertEquals(1, $events->where('type', PaymentEventType::Expired)->count());
        $this->assertFalse($events->contains('type', PaymentEventType::Failed));

        $this->actingAs($user)->get(route('shop.checkout.payment', $order->number))
            ->assertOk()
            ->assertSee('Délai de paiement dépassé')
            ->assertDontSee('Réessayer le paiement');
    }

    public function test_expiry_confirms_instead_of_cancelling_when_paytech_reports_payment(): void
    {
        $this->fakePayTech('completed');
        [, $order, $variant] = $this->placeOrder();

        $this->travel(31)->minutes();
        $this->artisan('payments:expire')->assertSuccessful();

        $this->assertEquals(OrderStatus::New, $order->fresh()->status);
        $this->assertEquals(PaymentStatus::Confirmed, $order->payment->fresh()->status);
        $this->assertEquals(2, $variant->fresh()->stock);
    }

    public function test_paytech_is_hidden_without_keys(): void
    {
        config(['payment.paytech.api_key' => null]);
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        Address::factory()->for($user)->create();

        $this->actingAs($user)->get(route('shop.checkout'))
            ->assertOk()
            ->assertDontSee('Wave, Orange Money ou carte');
    }
}

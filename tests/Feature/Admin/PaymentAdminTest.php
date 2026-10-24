<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\PaymentEventType;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\Order\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\Order\PaymentEventResource;
use App\Filament\Resources\Order\PaymentEventResource\Pages\ListPaymentEvents;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\Order\PaymentEvent;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Back-office des paiements (phase 5E) : statut réel + référence visibles,
 * journal consultable, vérification manuelle auprès du fournisseur.
 */
class PaymentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.paytech.api_key' => 'test-api-key',
            'payment.paytech.api_secret' => 'test-api-secret',
        ]);
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function paytechOrder(string $state = 'running'): Order
    {
        Http::fake([
            'paytech.sn/api/payment/request-payment' => Http::response(['success' => 1, 'token' => 'tok-1', 'redirect_url' => 'https://paytech.sn/payment/checkout/tok-1']),
            'paytech.sn/api/payment/get-status*' => Http::response(['success' => 1, 'payment' => ['state' => $state]]),
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 10000]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        $order = app(OrderService::class)->createFromCart($user, $cart, $address, 'paytech');
        app(PaymentManager::class)->find('paytech')->checkout($order->payment);

        return $order->fresh('payment');
    }

    public function test_the_order_page_shows_the_real_payment_status_and_its_journal(): void
    {
        $order = $this->paytechOrder();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('Statut réel')
            ->assertSee('En cours')
            ->assertSee('Journal du paiement')
            ->assertSee('Paiement initié')
            ->assertSee($order->number.'-1');
    }

    public function test_admin_can_reconcile_a_payment_with_the_provider(): void
    {
        $order = $this->paytechOrder('success');
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('verifyPayment')
            ->assertHasNoActionErrors();

        $this->assertEquals(PaymentStatus::Confirmed, $order->payment->fresh()->status);
        $this->assertTrue($order->payment->events()->where('type', PaymentEventType::StatusChecked)->exists());
    }

    public function test_reconciling_never_confirms_without_the_provider_saying_so(): void
    {
        $order = $this->paytechOrder('running');
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('verifyPayment');

        $this->assertEquals(PaymentStatus::Processing, $order->payment->fresh()->status);
    }

    public function test_cash_on_delivery_orders_have_nothing_to_verify(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $order = app(OrderService::class)->createFromCart($user, $cart, Address::factory()->for($user)->create(), 'cod');
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('verifyPayment');
    }

    public function test_the_verify_button_disappears_once_the_payment_is_received(): void
    {
        $order = $this->paytechOrder();
        app(PaymentService::class)->confirm($order->payment);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('verifyPayment');
    }

    public function test_the_payment_journal_lists_suspicious_notifications_and_is_read_only(): void
    {
        $suspect = PaymentEvent::create([
            'provider' => 'paytech',
            'type' => PaymentEventType::SuspiciousNotification,
            'payload' => ['ref_command' => 'AS-2026-FAUX00-1', 'motif' => 'signature invalide'],
            'ip' => '203.0.113.9',
        ]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ListPaymentEvents::class)
            ->assertCanSeeTableRecords([$suspect])
            ->assertSee('signature invalide')
            ->assertSee('203.0.113.9');

        $this->assertEquals('1', PaymentEventResource::getNavigationBadge());
        $this->assertFalse(PaymentEventResource::canCreate());
        $this->assertFalse(PaymentEventResource::canEdit($suspect));
        $this->assertFalse(PaymentEventResource::canDelete($suspect));
    }

    public function test_the_journal_is_reserved_to_admins(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(PaymentEventResource::getUrl('index'))
            ->assertRedirect();
    }

    public function test_the_customer_sees_the_payment_status_and_can_finish_paying(): void
    {
        $order = $this->paytechOrder();

        $this->actingAs($order->user)->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('En cours')
            ->assertSee('Finaliser le paiement')
            ->assertSee(route('shop.checkout.payment', $order->number), false);
    }
}

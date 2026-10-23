<?php

namespace Tests\Feature\Order;

use App\Enums\Order\PaymentEventType;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payment\CodProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PaymentEventTest extends TestCase
{
    use RefreshDatabase;

    private function payment(): Payment
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 10000]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        return app(OrderService::class)->createFromCart($user, $cart, $address, 'cod')->payment;
    }

    public function test_events_are_appended_with_the_current_payment_status(): void
    {
        $payment = $this->payment();

        $event = $payment->log(PaymentEventType::Initiated, ['amount' => 10000], '127.0.0.1');

        $this->assertEquals(PaymentEventType::Initiated, $event->type);
        $this->assertEquals('en_attente', $event->status);
        $this->assertEquals(['amount' => 10000], $event->payload);
        $this->assertCount(1, $payment->events);
    }

    public function test_the_payment_log_cannot_be_rewritten(): void
    {
        $event = $this->payment()->log(PaymentEventType::Initiated);

        $this->expectException(LogicException::class);

        $event->update(['type' => PaymentEventType::Confirmed]);
    }

    public function test_the_payment_log_cannot_be_deleted(): void
    {
        $event = $this->payment()->log(PaymentEventType::Initiated);

        $this->expectException(LogicException::class);

        $event->delete();
    }

    public function test_cash_on_delivery_needs_no_online_redirect(): void
    {
        $this->assertNull(app(CodProvider::class)->checkout($this->payment()));
    }
}

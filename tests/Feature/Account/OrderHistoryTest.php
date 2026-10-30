<?php

namespace Tests\Feature\Account;

use App\Enums\Order\OrderStatus;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    private function makeOrder(User $user): Order
    {
        Mail::fake();

        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        return app(OrderService::class)->createFromCart($user, $cart, $address, 'cod');
    }

    public function test_a_guest_cannot_access_order_history(): void
    {
        $this->get(route('account.orders.index'))->assertRedirect(route('login'));
    }

    public function test_a_customer_sees_their_own_orders_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->makeOrder($user);
        $theirs = $this->makeOrder($other);

        $response = $this->actingAs($user)->get(route('account.orders.index'));

        $response->assertOk();
        $response->assertSee($mine->number);
        $response->assertDontSee($theirs->number);
    }

    public function test_a_customer_cannot_open_someone_elses_order(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('account.orders.show', $order))
            ->assertForbidden();
    }

    public function test_a_customer_can_open_their_own_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_the_detail_shows_delivery_tracking(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $order->update(['delivery_assignee' => 'Moussa (moto)', 'delivery_note' => 'Appeler avant de passer.']);
        app(OrderService::class)->changeStatus($order, OrderStatus::Preparing);
        app(OrderService::class)->changeStatus($order->fresh(), OrderStatus::Shipped);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Expédiée')
            ->assertSee('Moussa (moto)')
            ->assertSee('Appeler avant de passer.');
    }
}

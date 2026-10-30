<?php

namespace Tests\Feature\Order;

use App\Enums\Order\OrderStatus;
use App\Mail\NewOrderForAdmin;
use App\Mail\OrderPlaced;
use App\Mail\OrderStatusChanged;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Mail::fake() ne rend jamais les vues : sans ce test, une variable
 * manquante dans un Blade d'email ne pèterait qu'en file de production,
 * silencieusement.
 */
class MailRenderTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    public function test_order_emails_render_without_error(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();
        $order = app(OrderService::class)->createFromCart($user, $cart, $address, 'cod');

        $this->assertStringContainsString($order->number, (new OrderPlaced($order))->render());
        $this->assertStringContainsString($order->number, (new OrderStatusChanged($order, OrderStatus::Preparing))->render());
        $this->assertStringContainsString($order->number, (new NewOrderForAdmin($order))->render());
    }
}

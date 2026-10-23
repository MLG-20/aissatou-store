<?php

namespace Tests\Feature\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Exceptions\OrderException;
use App\Mail\NewOrderForAdmin;
use App\Mail\OrderPlaced;
use App\Mail\OrderStatusChanged;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OrderService
    {
        return app(OrderService::class);
    }

    private function cartWithVariant(User $user, Product $product, ProductVariant $variant, int $quantity = 1): Cart
    {
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);

        return $cart;
    }

    public function test_creating_an_order_freezes_prices_and_empties_the_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 15000]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 5, 'price_override' => 18000]);
        $cart = $this->cartWithVariant($user, $product, $variant, 2);
        $address = Address::factory()->for($user)->create(['city' => 'Dakar']);

        $order = $this->service()->createFromCart($user, $cart, $address, 'cod');

        $this->assertMatchesRegularExpression('/^AS-\d{4}-[A-Z0-9]{6}$/', $order->number);
        $this->assertEquals(OrderStatus::New, $order->status);
        $this->assertEquals(36000, (float) $order->total);
        $this->assertEquals('Dakar', $order->city);
        $this->assertEquals(18000, (float) $order->items->first()->unit_price);
        $this->assertEquals(PaymentStatus::Pending, $order->payment_status);
        $this->assertEquals('cod', $order->payment->provider);
        $this->assertEquals(36000, (float) $order->payment->amount);
        // Le panier est vidé, le stock décrémenté à la validation.
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertEquals(3, $variant->fresh()->stock);
        // Création tracée dans l'historique.
        $this->assertCount(1, $order->history);
    }

    public function test_cancelling_an_order_releases_its_stock(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 5]);
        $address = Address::factory()->for($user)->create();
        $order = $this->service()->createFromCart($user, $this->cartWithVariant($user, $product, $variant, 2), $address, 'cod');
        $this->assertEquals(3, $variant->fresh()->stock);

        $this->service()->changeStatus($order, OrderStatus::Cancelled);

        $this->assertEquals(5, $variant->fresh()->stock);
    }

    public function test_the_total_is_recalculated_from_the_catalog_not_from_client_data(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 10000]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        // Le prix catalogue change entre l'ajout au panier et la validation :
        // la commande retient le prix réel en base, pas une valeur manipulée.
        $product->update(['price' => 25000]);

        $order = $this->service()->createFromCart($user, $cart, $address, 'cod');

        $this->assertEquals(25000, (float) $order->total);
    }

    public function test_a_past_order_never_changes_when_catalog_prices_change(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 10000]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        $order = $this->service()->createFromCart($user, $cart, $address, 'cod');
        $product->update(['price' => 99999]);

        $this->assertEquals(10000, (float) $order->fresh()->total);
        $this->assertEquals(10000, (float) $order->fresh()->items->first()->unit_price);
    }

    public function test_two_orders_on_the_last_item_do_not_oversell(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 1]);
        $product = $variant->product;
        $addressA = Address::factory()->create();
        $addressB = Address::factory()->create();
        $cartA = $this->cartWithVariant($addressA->user, $product, $variant, 1);
        $cartB = $this->cartWithVariant($addressB->user, $product, $variant, 1);

        $this->service()->createFromCart($addressA->user, $cartA, $addressA, 'cod');

        $this->expectException(OrderException::class);
        $this->service()->createFromCart($addressB->user, $cartB, $addressB, 'cod');

        $this->assertEquals(0, $variant->fresh()->stock);
        $this->assertEquals(1, Order::count());
    }

    public function test_an_order_cannot_use_someone_elses_address(): void
    {
        $user = User::factory()->create();
        $otherAddress = Address::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        try {
            $this->service()->createFromCart($user, $cart, $otherAddress, 'cod');
            $this->fail('Adresse d\'un autre utilisateur acceptée.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
        }

        $this->assertEquals(0, Order::count());
    }

    public function test_an_unknown_payment_provider_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        $this->expectException(OrderException::class);
        $this->service()->createFromCart($user, $cart, $address, 'wave');
    }

    public function test_status_transitions_are_guarded_and_logged(): void
    {
        // Sans fake, les emails partent en vraie file `database` et font
        // planter le processus PHP en environnement de test.
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();
        $order = $this->service()->createFromCart($user, $cart, $address, 'cod');

        $admin = Admin::factory()->create();

        // Nouvelle → livrée directement : interdit.
        try {
            $this->service()->changeStatus($order, OrderStatus::Delivered);
            $this->fail('Transition interdite acceptée.');
        } catch (OrderException) {
            $this->assertTrue(true);
        }

        $order = $this->service()->changeStatus($order, OrderStatus::Preparing, authorId: $admin->id);
        $order = $this->service()->changeStatus($order, OrderStatus::Shipped, authorId: $admin->id);
        $order = $this->service()->changeStatus($order, OrderStatus::Delivered, authorId: $admin->id);

        $this->assertEquals(OrderStatus::Delivered, $order->status);
        // À la livraison en paiement à la livraison, le paiement est confirmé.
        $this->assertEquals(PaymentStatus::Confirmed, $order->payment->status);
        $this->assertCount(4, $order->history);
        $this->assertEquals($admin->id, $order->history->last()->author_id);
    }

    public function test_confirmation_and_admin_emails_are_queued_on_order_creation(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        $order = $this->service()->createFromCart($user, $cart, $address, 'cod');

        Mail::assertQueued(OrderPlaced::class, fn ($mail) => $mail->hasTo($user->email));
        Mail::assertQueued(NewOrderForAdmin::class, function ($mail) use ($order): bool {
            return $mail->order->is($order);
        });
    }

    public function test_a_status_change_email_is_queued(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();
        $order = $this->service()->createFromCart($user, $cart, $address, 'cod');

        $this->service()->changeStatus($order, OrderStatus::Preparing);

        Mail::assertQueued(OrderStatusChanged::class, fn ($mail) => $mail->hasTo($user->email));
    }
}

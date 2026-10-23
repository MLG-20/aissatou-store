<?php

namespace Tests\Feature\Shop;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('shop.checkout'))->assertRedirect(route('login'));
        $this->post(route('shop.checkout.store'))->assertRedirect(route('login'));
    }

    public function test_the_cart_page_leads_to_the_checkout(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->get(route('shop.cart'))
            ->assertOk()
            ->assertSee(route('shop.checkout'), false)
            ->assertSee('Passer commande');
    }

    public function test_an_empty_cart_redirects_back_to_the_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('shop.checkout'))
            ->assertRedirect(route('shop.cart'));
    }

    public function test_a_customer_can_place_an_order_end_to_end(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 12000]);
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 4, 'price_override' => null]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        $address = Address::factory()->for($user)->create(['is_default' => true]);

        $response = $this->actingAs($user)->get(route('shop.checkout'));
        $response->assertOk();
        $response->assertSee($product->name);
        $response->assertSee('Paiement à la livraison');

        $response = $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'cod',
        ]);

        $order = Order::first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('shop.checkout.success', $order->number));
        $this->assertEquals(24000, (float) $order->total);
        $this->assertEquals(2, $variant->fresh()->stock);

        // Page de confirmation visible par le propriétaire.
        $this->actingAs($user)->get(route('shop.checkout.success', $order->number))
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_someone_elses_order_confirmation_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $owner->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'cod',
        ]);

        $order = Order::first();

        $this->actingAs($intruder)->get(route('shop.checkout.success', $order->number))
            ->assertForbidden();
    }

    public function test_someone_elses_address_is_rejected_by_validation(): void
    {
        $user = User::factory()->create();
        $otherAddress = Address::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $otherAddress->id,
            'provider' => 'cod',
        ])->assertSessionHasErrors('address_id');

        $this->assertEquals(0, Order::count());
    }
}

<?php

namespace Tests\Feature\Delivery;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Delivery\DeliveryZone;
use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

class DeliveryCheckoutTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    private function cartFor(User $user, int $quantity = 1): Cart
    {
        $product = Product::factory()->create(['price' => 10000]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);

        return $cart;
    }

    public function test_the_zone_fee_is_added_and_frozen_at_purchase(): void
    {
        Mail::fake();

        DeliveryZone::create(['name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1500, 'is_active' => true]);

        $user = User::factory()->create();
        $cart = $this->cartFor($user);
        $address = Address::factory()->for($user)->create(['city' => 'Dakar']);

        $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'cod',
        ]);

        $order = Order::first();
        $this->assertEquals(1500, (float) $order->shipping_total);
        $this->assertEquals('Dakar', $order->shipping_zone);
        $this->assertEquals(11500, (float) $order->total);

        // La zone change après l'achat : la commande garde le frais du jour J.
        DeliveryZone::where('city', 'Dakar')->update(['fee' => 5000]);

        $this->assertEquals(11500, (float) $order->fresh()->total);
    }

    public function test_an_unknown_city_uses_the_default_zone(): void
    {
        Mail::fake();

        DeliveryZone::create(['name' => 'Autres zones', 'city' => null, 'fee' => 3000, 'is_active' => true]);

        $user = User::factory()->create();
        $cart = $this->cartFor($user);
        $address = Address::factory()->for($user)->create(['city' => 'Ziguinchor']);

        $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'cod',
        ]);

        $order = Order::first();
        $this->assertEquals(3000, (float) $order->shipping_total);
        $this->assertEquals(13000, (float) $order->total);
    }

    public function test_without_zones_shipping_is_zero_and_checkout_still_works(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $cart = $this->cartFor($user);
        $address = Address::factory()->for($user)->create(['city' => 'Dakar']);

        $response = $this->actingAs($user)->get(route('shop.checkout'));
        $response->assertOk();

        $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'cod',
        ]);

        $order = Order::first();
        $this->assertEquals(0, (float) $order->shipping_total);
        $this->assertEquals(10000, (float) $order->total);
    }
}

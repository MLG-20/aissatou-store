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
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Sans limite, un compte pouvait enchaîner les commandes et bloquer tout le
 * stock, puisqu'une commande le réserve dès sa création.
 */
class CheckoutRateLimitTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function placeOrder(User $user, Address $address, ProductVariant $variant): TestResponse
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
        $cart->items()->delete();
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        return $this->actingAs($user)->from(route('shop.checkout'))
            ->post(route('shop.checkout.store'), ['address_id' => $address->id, 'provider' => 'cod']);
    }

    private function customer(): array
    {
        $user = User::factory()->create();

        return [$user, Address::factory()->for($user)->create(['is_default' => true])];
    }

    public function test_orders_in_a_row_are_stopped_and_the_stock_is_safe(): void
    {
        $variant = ProductVariant::factory()->for(Product::factory())->create(['stock' => 100]);
        [$user, $address] = $this->customer();

        for ($i = 0; $i < 12; $i++) {
            $this->placeOrder($user, $address, $variant);
        }

        $this->assertSame(5, Order::query()->count());
        $this->assertSame(95, $variant->fresh()->stock);
    }

    public function test_the_customer_is_told_why_in_french(): void
    {
        $variant = ProductVariant::factory()->for(Product::factory())->create(['stock' => 100]);
        [$user, $address] = $this->customer();

        for ($i = 0; $i < 5; $i++) {
            $this->placeOrder($user, $address, $variant);
        }

        $this->placeOrder($user, $address, $variant)
            ->assertRedirect(route('shop.checkout'))
            ->assertSessionHasErrors(['cart' => 'Vous avez passé plusieurs commandes à la suite. Merci de patienter quelques instants avant de réessayer.']);
    }

    public function test_the_limit_is_per_account(): void
    {
        $variant = ProductVariant::factory()->for(Product::factory())->create(['stock' => 100]);
        [$first, $firstAddress] = $this->customer();
        [$second, $secondAddress] = $this->customer();

        for ($i = 0; $i < 6; $i++) {
            $this->placeOrder($first, $firstAddress, $variant);
        }

        $this->placeOrder($second, $secondAddress, $variant);

        $this->assertSame(1, Order::query()->where('user_id', $second->id)->count());
    }

    public function test_the_limit_lifts_after_a_minute_but_an_hourly_cap_remains(): void
    {
        $variant = ProductVariant::factory()->for(Product::factory())->create(['stock' => 100]);
        [$user, $address] = $this->customer();

        for ($minute = 0; $minute < 6; $minute++) {
            for ($i = 0; $i < 5; $i++) {
                $this->placeOrder($user, $address, $variant);
            }
            $this->travel(61)->seconds();
        }

        $this->assertSame(20, Order::query()->count());
    }
}

<?php

namespace Tests\Feature\Shop;

use App\Models\Cart\Cart;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * « Acheter maintenant » : l'article passe par le panier et la cliente
 * arrive droit sur la commande, sans doubler un article déjà au panier.
 */
class BuyNowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_now_is_the_main_action_of_the_product_page(): void
    {
        $product = Product::factory()->create();

        $this->get(route('shop.product', $product))
            ->assertOk()
            ->assertSeeInOrder(['Acheter maintenant', 'Ajouter au panier'])
            ->assertSee('name="buy_now"', false);
    }

    public function test_buy_now_leads_straight_to_the_checkout(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)
            ->post(route('shop.cart.store'), ['product_id' => $product->id, 'quantity' => 1, 'buy_now' => 1])
            ->assertRedirect(route('shop.checkout'));

        $this->actingAs($user)->get(route('shop.checkout'))->assertOk()->assertSee($product->name)->assertSee('Modifier mon panier');
    }

    public function test_a_visitor_logs_in_and_lands_on_the_checkout_with_the_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // Même session « navigateur » d'un bout à l'autre, comme dans CartTest.
        $session = Str::random(40);
        $browser = fn () => $this->withCookie(config('session.cookie'), $session);

        $browser()->post(route('shop.cart.store'), ['product_id' => $product->id, 'quantity' => 1, 'buy_now' => 1])
            ->assertRedirect(route('shop.checkout'));
        $browser()->get(route('shop.checkout'))->assertRedirect(route('login'));

        $browser()->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('shop.checkout'));

        $this->assertSame(1, (int) Cart::where('user_id', $user->id)->first()->items()->sum('quantity'));
    }

    public function test_an_item_already_in_the_cart_is_not_bought_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 10]);
        $payload = ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1];

        $this->actingAs($user)->post(route('shop.cart.store'), $payload);
        $this->actingAs($user)->post(route('shop.cart.store'), $payload + ['buy_now' => 1]);

        $this->assertSame(1, (int) Cart::where('user_id', $user->id)->first()->items()->sum('quantity'));

        // Le simple ajout, lui, continue de cumuler.
        $this->actingAs($user)->post(route('shop.cart.store'), $payload);
        $this->assertSame(2, (int) Cart::where('user_id', $user->id)->first()->items()->sum('quantity'));
    }

    public function test_an_out_of_stock_choice_stays_on_the_product_page(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 1]);

        $this->actingAs($user)->from(route('shop.product', $product))
            ->post(route('shop.cart.store'), ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 3, 'buy_now' => 1])
            ->assertRedirect(route('shop.product', $product))
            ->assertSessionHasErrors('variant_id');
    }
}

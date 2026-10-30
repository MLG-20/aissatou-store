<?php

namespace Tests\Feature\Shop;

use App\Filament\Pages\SiteSettings;
use App\Mail\OrderPlaced;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Content\SiteSetting;
use App\Models\Customer\Address;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Paiement à la livraison activable en admin, coupé par défaut : au lancement
 * seul le paiement en ligne est proposé.
 */
class CashOnDeliverySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_on_delivery_is_off_by_default_and_refused_at_checkout(): void
    {
        [$user, $address] = $this->customerWithCart();

        $this->actingAs($user)->get(route('shop.checkout'))
            ->assertOk()
            ->assertDontSee('Paiement à la livraison');

        $this->actingAs($user)->post(route('shop.checkout.store'), ['address_id' => $address->id, 'provider' => 'cod'])
            ->assertSessionHasErrors('provider');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_owner_turns_it_on_from_the_settings(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(SiteSettings::class)
            ->assertSet('data.cash_on_delivery_enabled', false)
            ->set('data.cash_on_delivery_enabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(SiteSetting::current()->fresh()->cash_on_delivery_enabled);

        [$user, $address] = $this->customerWithCart();

        $this->actingAs($user)->get(route('shop.checkout'))->assertSee('Paiement à la livraison');
        $this->actingAs($user)->post(route('shop.checkout.store'), ['address_id' => $address->id, 'provider' => 'cod']);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_the_product_page_reassurance_follows_the_setting(): void
    {
        $product = Product::factory()->create();

        $this->get(route('shop.product', $product->slug))
            ->assertSee('Paiement sécurisé en ligne')
            ->assertDontSee('Payez à la réception');

        $this->enableCashOnDelivery();

        $this->get(route('shop.product', $product->slug))
            ->assertSee('Payez à la réception');
    }

    public function test_the_order_email_does_not_promise_cash_on_delivery_for_an_online_payment(): void
    {
        config(['payment.paytech.api_key' => 'key', 'payment.paytech.api_secret' => 'secret']);
        [$user, $address] = $this->customerWithCart();
        $order = app(OrderService::class)->createFromCart($user, Cart::first(), $address, 'paytech');

        $mail = new OrderPlaced($order);

        $this->assertStringContainsString('paiement en ligne', $mail->render());
        $this->assertStringNotContainsString('à la livraison', $mail->render());
        $mail->assertHasSubject('Votre commande '.$order->number.' est enregistrée');
    }

    /** @return array{User, Address} */
    private function customerWithCart(): array
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        return [$user, Address::factory()->for($user)->create(['is_default' => true])];
    }
}

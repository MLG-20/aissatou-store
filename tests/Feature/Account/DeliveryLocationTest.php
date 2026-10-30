<?php

namespace Tests\Feature\Account;

use App\Filament\Resources\Order\OrderResource\Pages\ViewOrder;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Position exacte de livraison : GPS du téléphone ou lien Maps, sur l'adresse
 * du carnet, recopiée dans la commande et ouverte par l'admin.
 */
class DeliveryLocationTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    public function test_an_address_keeps_the_shared_gps_point(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload([
            'latitude' => '14.6697', 'longitude' => '-17.4381', 'location_accuracy' => '18',
        ]))->assertSessionHasNoErrors();

        $address = $user->addresses()->first();
        $this->assertSame(14.6697, $address->latitude);
        $this->assertSame(18, $address->location_accuracy);
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=14.6697,-17.4381', $address->locationUrl());
    }

    public function test_a_point_outside_senegal_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload([
            'latitude' => '48.8566', 'longitude' => '2.3522',
        ]))->assertSessionHasErrors(['latitude' => 'Cette position est hors du Sénégal. Partagez-la depuis le lieu de livraison, ou retirez-la.']);

        $this->assertDatabaseCount('customer_addresses', 0);
    }

    public function test_only_google_or_apple_maps_links_are_accepted(): void
    {
        $user = User::factory()->create();

        foreach (['https://evil.example/maps', 'javascript:alert(1)', 'http://maps.app.goo.gl/abc', 'https://goo.gl/xyz'] as $link) {
            $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload(['maps_link' => $link]))
                ->assertSessionHasErrors('maps_link');
        }

        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload(['maps_link' => 'https://maps.app.goo.gl/AbC123']))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://maps.app.goo.gl/AbC123', $user->addresses()->first()->locationUrl());
    }

    public function test_removing_the_position_clears_it(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['latitude' => 14.7, 'longitude' => -17.4, 'location_accuracy' => 20]);

        $this->actingAs($user)->put(route('account.addresses.update', $address), $this->addressPayload([
            'latitude' => '', 'longitude' => '', 'location_accuracy' => '', 'maps_link' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertFalse($address->fresh()->hasLocation());
    }

    public function test_the_add_form_is_not_prefilled_with_an_existing_address(): void
    {
        $user = User::factory()->create();
        Address::factory()->for($user)->create(['recipient_name' => 'Awa Ndiaye', 'latitude' => 14.7, 'longitude' => -17.4]);

        $page = $this->actingAs($user)->get(route('account.addresses.index'))->assertOk()->getContent();

        // Une seule fois : le formulaire de modification, pas celui d'ajout.
        $this->assertSame(1, substr_count($page, 'value="Awa Ndiaye"'));
    }

    public function test_a_position_shared_at_checkout_goes_to_the_order_and_the_address_book(): void
    {
        [$user, $address] = $this->customerWithCart();

        $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id, 'provider' => 'cod',
            'latitude' => '14.6697', 'longitude' => '-17.4381', 'location_accuracy' => '25',
        ])->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertSame(14.6697, $order->latitude);
        $this->assertSame(25, $order->location_accuracy);
        $this->assertSame(-17.4381, $address->fresh()->longitude);
    }

    public function test_checkout_never_overwrites_a_position_already_in_the_address_book(): void
    {
        [$user, $address] = $this->customerWithCart(['latitude' => 14.7, 'longitude' => -17.45]);

        $this->actingAs($user)->post(route('shop.checkout.store'), [
            'address_id' => $address->id, 'provider' => 'cod',
            'latitude' => '14.6', 'longitude' => '-17.3',
        ]);

        $this->assertSame(14.7, Order::first()->latitude);
        $this->assertSame(14.7, $address->fresh()->latitude);
    }

    public function test_the_admin_and_the_customer_see_the_position(): void
    {
        Mail::fake();
        [$user, $address] = $this->customerWithCart(['latitude' => 14.6697, 'longitude' => -17.4381, 'location_accuracy' => 18]);
        $order = app(OrderService::class)->createFromCart($user, Cart::first(), $address, 'cod');

        $this->actingAs(Admin::factory()->create(), 'admin');
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('Ouvrir dans Google Maps (à 18 m près)')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=14.6697,-17.4381', false);

        $this->actingAs($user)->get(route('account.orders.show', $order))
            ->assertSee('Position exacte transmise au livreur');
    }

    /** @return array{User, Address} */
    private function customerWithCart(array $address = []): array
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        return [$user, Address::factory()->for($user)->create(['is_default' => true, ...$address])];
    }

    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Aminata Diop',
            'phone' => '771234567',
            'city' => 'Dakar',
            'address_line' => 'Rue 10, Sacré-Cœur',
        ], $overrides);
    }
}

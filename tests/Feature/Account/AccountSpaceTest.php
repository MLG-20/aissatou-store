<?php

namespace Tests\Feature\Account;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Models\Wishlist\Wishlist;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Espace cliente : cadre commun (salutation, chiffres, menu, déconnexion),
 * tableau de bord vivant (commande en cours, coups de cœur, profil) et liste
 * d'envies rangée dans l'espace une fois connectée.
 */
class AccountSpaceTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    private function order(User $user): Order
    {
        Mail::fake();

        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        return app(OrderService::class)->createFromCart($user, $cart, Address::factory()->for($user)->create(), 'cod');
    }

    public function test_the_greeting_follows_the_time_of_day(): void
    {
        $user = User::factory()->create(['name' => 'Aminata Diallo']);

        $this->travelTo(today()->setTime(10, 0));
        $this->actingAs($user)->get(route('dashboard'))->assertSeeText('Bonjour Aminata');

        $this->travelTo(today()->setTime(20, 0));
        $this->actingAs($user)->get(route('dashboard'))->assertSeeText('Bonsoir Aminata');
    }

    public function test_the_dashboard_features_the_order_in_progress_and_the_profile_to_complete(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $order = $this->order($user);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Votre commande en cours')
            ->assertSee($order->number)
            ->assertSee('Suivre ma commande')
            ->assertSee('Reçue')
            // Compte, adresse et commande : 3 jalons sur 4, il manque le téléphone.
            ->assertSee('75 %')
            ->assertSee('Téléphone ajouté')
            ->assertSee('Se déconnecter');
    }

    public function test_a_new_customer_is_invited_to_a_first_order(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Votre première pièce vous attend')
            ->assertSee('Vos coups de cœur');
    }

    public function test_every_account_page_shares_the_same_frame(): void
    {
        $user = User::factory()->create(['name' => 'Fatou Sow']);

        foreach (['dashboard', 'account.orders.index', 'account.addresses.index', 'profile.edit'] as $route) {
            $this->actingAs($user)->get(route($route))
                ->assertOk()
                ->assertSee('Votre espace')
                ->assertSee('Mes coups de cœur')
                ->assertSee('Se déconnecter');
        }
    }

    public function test_the_wishlist_lives_in_the_account_once_logged_in(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);
        Wishlist::create(['user_id' => $user->id])->items()->create(['product_id' => $product->id]);

        $this->actingAs($user)->get(route('shop.wishlist'))
            ->assertOk()
            ->assertSee('Votre espace')
            ->assertSee('Mes coups de cœur')
            ->assertSee($product->name);

        $this->get(route('dashboard'))->assertSee($product->name);
    }

    public function test_a_guest_keeps_the_shop_wishlist_page(): void
    {
        $this->get(route('shop.wishlist'))
            ->assertOk()
            ->assertSee("Ma liste d'envies", false)
            ->assertDontSee('Se déconnecter');
    }
}

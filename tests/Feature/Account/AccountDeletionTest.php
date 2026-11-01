<?php

namespace Tests\Feature\Account;

use App\Enums\Order\OrderStatus;
use App\Filament\Resources\Customer\CustomerResource;
use App\Filament\Resources\Order\OrderResource;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Models\Wishlist\Wishlist;
use App\Services\OrderService;
use App\Services\StatisticsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Suppression de compte : les données personnelles partent, mais les commandes
 * restent (obligation comptable), et une commande en cours bloque la demande.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function customerWithOrder(): array
    {
        $user = User::factory()->create(['name' => 'Awa Diop', 'email' => 'awa@example.com', 'phone' => '771234567']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['stock' => 5]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create(['is_default' => true]);

        return [$user, app(OrderService::class)->createFromCart($user, $cart, $address, 'cod')];
    }

    private function deliver(Order $order): void
    {
        foreach ([OrderStatus::Preparing, OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
            $order = app(OrderService::class)->changeStatus($order->fresh(), $status);
        }
    }

    public function test_the_orders_survive_and_the_personal_data_is_gone(): void
    {
        [$user, $order] = $this->customerWithOrder();
        $this->deliver($order);
        $recipient = $order->recipient_name;
        Cart::query()->firstOrCreate(['user_id' => $user->id]);
        Wishlist::create(['user_id' => $user->id]);
        $user->createToken('telephone');
        DB::table('sessions')->insert(['id' => 'autre-appareil', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $revenue = app(StatisticsService::class)->summary(now(), now())['current']['revenue'];

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        $kept = Order::query()->findOrFail($order->id);
        $this->assertSame($recipient, $kept->recipient_name);
        $this->assertNotNull($kept->payment);
        $this->assertCount(1, $kept->items);
        $this->assertEquals($revenue, app(StatisticsService::class)->summary(now(), now())['current']['revenue']);

        $user->refresh();
        $this->assertNotNull($user->anonymized_at);
        $this->assertSame('Compte supprimé', $user->name);
        $this->assertStringNotContainsString('awa', $user->email);
        $this->assertNull($user->phone);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(0, Address::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, Cart::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, Wishlist::query()->where('user_id', $user->id)->count());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_the_old_password_no_longer_opens_the_account(): void
    {
        [$user, $order] = $this->customerWithOrder();
        $this->deliver($order);
        $this->actingAs($user)->delete('/profile', ['password' => 'password']);
        Auth::forgetGuards();

        $this->post('/login', ['email' => 'awa@example.com', 'password' => 'password']);
        $this->assertGuest();

        $this->post('/login', ['email' => $user->fresh()->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_the_same_email_can_open_a_new_account(): void
    {
        [$user, $order] = $this->customerWithOrder();
        $this->deliver($order);
        $this->actingAs($user)->delete('/profile', ['password' => 'password']);

        $this->assertSame(0, User::query()->where('email', 'awa@example.com')->count());
    }

    public function test_an_order_in_progress_blocks_the_deletion(): void
    {
        [$user, $order] = $this->customerWithOrder();

        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertNull($user->anonymized_at);
        $this->assertSame('awa@example.com', $user->email);
        $this->assertTrue($order->fresh()->user->is($user));
    }

    public function test_a_wrong_password_changes_nothing_even_with_a_finished_order(): void
    {
        [$user, $order] = $this->customerWithOrder();
        $this->deliver($order);

        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'mauvais'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertSame('awa@example.com', $user->fresh()->email);
    }

    public function test_an_account_without_orders_is_really_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertNull(User::query()->find($user->id));
    }

    public function test_a_direct_deletion_can_never_take_the_orders_with_it(): void
    {
        [$user, $order] = $this->customerWithOrder();

        try {
            $user->delete();
            $this->fail('La suppression aurait dû être refusée par la base.');
        } catch (QueryException) {
            $this->assertNotNull(Order::query()->find($order->id));
        }
    }

    public function test_the_admin_keeps_the_order_but_no_longer_lists_the_customer(): void
    {
        [$user, $order] = $this->customerWithOrder();
        $this->deliver($order);
        $this->actingAs($user)->delete('/profile', ['password' => 'password']);
        Auth::forgetGuards();

        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->assertSame(0, CustomerResource::getEloquentQuery()->whereKey($user->id)->count());
        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Compte supprimé')
            ->assertDontSee('awa@example.com');
    }
}

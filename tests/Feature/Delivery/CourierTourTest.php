<?php

namespace Tests\Feature\Delivery;

use App\Enums\Order\OrderStatus;
use App\Filament\Resources\Delivery\CourierResource\Pages\ListCouriers;
use App\Jobs\SendOrderWhatsApp;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Delivery\Courier;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Delivery\CourierService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * « Ma tournée » : lien personnel du livreur, révocable, qui liste toutes
 * ses commandes en cours.
 */
class CourierTourTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Bus::fake([SendOrderWhatsApp::class]);
    }

    public function test_the_tour_lists_only_his_orders_in_progress(): void
    {
        $moussa = Courier::factory()->create(['name' => 'Moussa']);
        $mine = $this->orderFor($moussa);
        $other = $this->orderFor(Courier::factory()->create());
        $done = $this->orderFor($moussa);
        app(OrderService::class)->changeStatus($done, OrderStatus::Shipped);
        app(OrderService::class)->changeStatus($done->fresh(), OrderStatus::Delivered);

        $this->get($this->tourPath($moussa))
            ->assertOk()
            ->assertSee('Ma tournée')
            ->assertSee($mine->number)
            ->assertDontSee($other->number)
            ->assertDontSee($done->number)
            ->assertSee('1 livrée aujourd', false);
    }

    public function test_the_courier_works_from_his_tour(): void
    {
        $moussa = Courier::factory()->create();
        $order = $this->orderFor($moussa);
        $tour = $this->tourPath($moussa);

        $this->post($tour.'/'.$order->number, ['action' => 'depart'])->assertRedirect($tour);
        $this->post($tour.'/'.$order->number, ['action' => 'livree'])->assertRedirect($tour);

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_a_tour_link_cannot_touch_someone_elses_order(): void
    {
        $moussa = Courier::factory()->create();
        $other = $this->orderFor(Courier::factory()->create());

        $this->post($this->tourPath($moussa).'/'.$other->number, ['action' => 'depart'])->assertForbidden();

        $this->assertSame(OrderStatus::Preparing, $other->fresh()->status);
    }

    public function test_only_a_hash_is_stored_and_a_new_link_kills_the_old_one(): void
    {
        $moussa = Courier::factory()->create();
        $old = $this->tourPath($moussa);

        $token = substr($old, strlen('/tournee/'));
        $this->assertSame(hash('sha256', $token), $moussa->fresh()->tour_token_hash);
        $this->assertDatabaseMissing('couriers', ['tour_token_hash' => $token]);

        $new = $this->tourPath($moussa);

        $this->get($old)->assertForbidden()->assertSee('Ce lien n\'est plus valable', false);
        $this->get($new)->assertOk();
    }

    public function test_revoking_or_deactivating_closes_the_tour(): void
    {
        $moussa = Courier::factory()->create();
        $tour = $this->tourPath($moussa);

        $moussa->update(['is_active' => false]);
        $this->get($tour)->assertForbidden();

        $moussa->update(['is_active' => true]);
        app(CourierService::class)->revokeTourLink($moussa);
        $this->get($tour)->assertForbidden();
    }

    public function test_the_owner_sends_then_revokes_the_link_from_the_list(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $moussa = Courier::factory()->create(['phone' => '77 123 45 67']);

        Livewire::test(ListCouriers::class)
            ->callTableAction('sendTourLink', $moussa)
            ->assertNotified('Lien « Ma tournée » créé pour '.$moussa->name);

        $this->assertNotNull($moussa->fresh()->tour_token_hash);
        $this->assertDatabaseHas('admin_activities', ['action' => 'courier.tour_link_sent', 'subject_id' => $moussa->id]);

        Livewire::test(ListCouriers::class)->callTableAction('revokeTourLink', $moussa->fresh());

        $this->assertNull($moussa->fresh()->tour_token_hash);
        $this->assertDatabaseHas('admin_activities', ['action' => 'courier.tour_link_revoked']);
    }

    /** Lien relatif, pour rester sur l'hôte des tests. */
    private function tourPath(Courier $courier): string
    {
        return parse_url(app(CourierService::class)->issueTourLink($courier), PHP_URL_PATH);
    }

    private function orderFor(Courier $courier): Order
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        $orders = app(OrderService::class);
        $order = $orders->changeStatus($orders->createFromCart($user, $cart, Address::factory()->for($user)->create(), 'cod'), OrderStatus::Preparing);
        $order->update(['courier_id' => $courier->id]);

        return $order;
    }
}

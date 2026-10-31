<?php

namespace Tests\Feature\Delivery;

use App\Enums\Order\OrderStatus;
use App\Filament\Resources\Delivery\CourierResource\Pages\CreateCourier;
use App\Filament\Resources\Delivery\CourierResource\Pages\ListCouriers;
use App\Filament\Resources\Order\OrderResource\Pages\ViewOrder;
use App\Jobs\SendOrderWhatsApp;
use App\Mail\OrderStatusChanged;
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
 * Livreurs : liste en admin, choix sur la commande, page sans compte ouverte
 * par lien signé (« je pars », « livrée »).
 */
class CourierTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Bus::fake([SendOrderWhatsApp::class]);
    }

    public function test_the_owner_adds_a_courier_with_a_senegalese_number(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateCourier::class)
            ->fillForm(['name' => 'Moussa', 'phone' => '12345', 'type' => 'interne'])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        Livewire::test(CreateCourier::class)
            ->fillForm(['name' => 'Moussa', 'phone' => '77 123 45 67', 'type' => 'interne', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('couriers', ['name' => 'Moussa', 'type' => 'interne']);
    }

    public function test_a_courier_who_already_delivered_cannot_be_deleted(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $busy = Courier::factory()->create();
        $this->orderFor($busy);
        $idle = Courier::factory()->create();

        Livewire::test(ListCouriers::class)
            ->assertTableActionHidden('delete', $busy)
            ->assertTableActionVisible('delete', $idle);
    }

    public function test_the_owner_picks_a_courier_on_the_order(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $order = $this->orderFor(null);
        $moussa = Courier::factory()->create(['name' => 'Moussa']);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('assignDelivery', data: ['courier_id' => $moussa->id, 'delivery_note' => null])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame($moussa->id, $order->courier_id);
        // Le nom reste ce que voit la cliente dans son suivi.
        $this->assertSame('Moussa', $order->delivery_assignee);
    }

    public function test_send_to_courier_opens_a_prefilled_whatsapp_with_the_signed_link(): void
    {
        $courier = Courier::factory()->create(['name' => 'Moussa', 'phone' => '77 123 45 67']);
        $order = $this->orderFor($courier, ['latitude' => 14.6697, 'longitude' => -17.4381]);

        $url = app(CourierService::class)->whatsAppUrl($order);
        $text = rawurldecode(substr($url, strpos($url, '?text=') + 6));

        $this->assertStringStartsWith('https://wa.me/221771234567?text=', $url);
        $this->assertStringContainsString('livraison '.$order->number, $text);
        // Itinéraire sur sa page, pas dans le message : un seul lien, lisible.
        $this->assertStringNotContainsString('google.com/maps', $text);
        $this->assertStringContainsString('À encaisser : ', $text);
        $this->assertStringContainsString('/livraison/'.$order->number.'?courier='.$courier->id, $text);
    }

    public function test_the_courier_leaves_then_delivers_from_his_page(): void
    {
        $courier = Courier::factory()->create(['name' => 'Moussa']);
        $order = $this->orderFor($courier);
        $link = $this->relativeLink($order);

        $this->get($link)->assertOk()->assertSee($order->recipient_name)->assertSee('J\'ai le colis, je pars', false);

        $this->post($link, ['action' => 'depart'])->assertRedirect($link);
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);

        $this->post($link, ['action' => 'livree'])->assertRedirect($link);
        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        // Paiement à la livraison : encaissé avec la remise du colis.
        $this->assertSame('confirme', $order->payment_status->value);

        $this->assertSame('Moussa (livreur)', $order->history()->reorder('id', 'desc')->first()->authorLabel());
        // Même chaîne que depuis l'admin : la cliente est prévenue.
        Mail::assertQueued(OrderStatusChanged::class, 3); // préparation (admin), expédiée, livrée

        // Terminée : plus aucune donnée de la cliente sur la page.
        $this->get($link)->assertOk()->assertSee('Commande terminée')->assertDontSee($order->recipient_name);
    }

    public function test_a_double_tap_changes_nothing(): void
    {
        $order = $this->orderFor(Courier::factory()->create());
        $link = $this->relativeLink($order);

        $this->post($link, ['action' => 'depart']);
        $this->post($link, ['action' => 'depart'])->assertSessionHas('courier_error');

        $this->assertSame(3, $order->history()->count()); // création, préparation, expédiée : une seule fois
    }

    public function test_a_dead_link_shows_nothing(): void
    {
        $courier = Courier::factory()->create();
        $order = $this->orderFor($courier);
        $link = $this->relativeLink($order);

        // Signature trafiquée.
        $this->get(str_replace('courier='.$courier->id, 'courier=999', $link))->assertForbidden()->assertDontSee($order->recipient_name);

        // Expiré.
        $this->travel(CourierService::ORDER_LINK_DAYS + 1)->days();
        $this->get($link)->assertForbidden()->assertSee('Ce lien n\'est plus valable', false);
        $this->travelBack();

        // Livreur remplacé, puis livreur désactivé.
        $order->update(['courier_id' => Courier::factory()->create()->id]);
        $this->post($link, ['action' => 'depart'])->assertForbidden();
        $order->update(['courier_id' => $courier->id]);
        $courier->update(['is_active' => false]);
        $this->get($link)->assertForbidden();

        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    private function relativeLink(Order $order): string
    {
        $url = app(CourierService::class)->orderLink($order);

        return substr($url, strlen(url('/')));
    }

    /** Commande en préparation, payable à la livraison, confiée à $courier. */
    private function orderFor(?Courier $courier, array $address = []): Order
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create($address);

        $orders = app(OrderService::class);
        $order = $orders->createFromCart($user, $cart, $address, 'cod');
        $order = $orders->changeStatus($order, OrderStatus::Preparing);
        $order->update(['courier_id' => $courier?->id]);

        return $order;
    }
}

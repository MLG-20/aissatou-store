<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Order\OrderResource\Pages\ListOrders;
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
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 10000]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();

        return app(OrderService::class)->createFromCart($user, $cart, $address, 'cod');
    }

    public function test_admin_can_list_orders_in_the_panel(): void
    {
        $order = $this->makeOrder();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ListOrders::class)
            ->assertSee($order->number);
    }

    public function test_admin_can_change_the_status_from_the_view_page(): void
    {
        $order = $this->makeOrder();
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('changeStatus', data: ['status' => 'preparation'])
            ->assertHasNoActionErrors();

        $this->assertEquals('preparation', $order->fresh()->status->value);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'to_status' => 'preparation',
            'author_id' => $admin->id,
        ]);
    }

    public function test_admin_can_download_the_invoice_as_pdf(): void
    {
        $order = $this->makeOrder();
        $this->actingAs(Admin::factory()->create(), 'admin');

        $response = $this->get(route('admin.orders.invoice', $order));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        // Le flux PDF est compressé : on vérifie la signature, pas le texte.
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_a_client_cannot_download_someone_elses_invoice(): void
    {
        $order = $this->makeOrder();

        // Invité : redirigé vers la connexion.
        $this->get(route('admin.orders.invoice', $order))->assertRedirect();

        // Client connecté (guard web) : pas le guard admin → redirigé vers la
        // connexion, jamais servi.
        $this->actingAs(User::factory()->create())->get(route('admin.orders.invoice', $order))
            ->assertRedirect();
    }
}

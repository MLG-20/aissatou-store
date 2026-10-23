<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\Customer\CustomerResource;
use App\Filament\Resources\Customer\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\Customer\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\Customer\CustomerResource\RelationManagers\OrdersRelationManager;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 6E : segmentation (réguliers, panier abandonné, inactifs) selon les
 * règles validées, historique d'achats, et données personnelles réservées à
 * l'admin complet (§3 du cahier des charges).
 */
class CustomerSegmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
    }

    private function purchase(User $user, float $total, PaymentStatus $status, ?string $confirmedAt): void
    {
        $at = $confirmedAt ?? now()->toDateTimeString();
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $user->id, 'number' => 'AS-2026-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::New->value, 'payment_status' => $status->value,
            'subtotal' => $total, 'discount_total' => 0, 'shipping_total' => 0, 'total' => $total,
            'recipient_name' => $user->name, 'phone' => '770000000', 'city' => 'Dakar', 'address_line' => 'Rue 1',
            'created_at' => $at, 'updated_at' => $at,
        ]);
        DB::table('payments')->insert([
            'order_id' => $orderId, 'provider' => 'cod', 'status' => $status->value, 'amount' => $total,
            'confirmed_at' => $confirmedAt, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** @return list<int> */
    private function ids(string $scope): array
    {
        return User::query()->{$scope}()->orderBy('id')->pluck('id')->all();
    }

    public function test_regular_customers_have_two_paid_orders_within_six_months(): void
    {
        $regular = User::factory()->create();
        $this->purchase($regular, 10000, PaymentStatus::Confirmed, '2026-09-01 10:00:00');
        $this->purchase($regular, 10000, PaymentStatus::Confirmed, '2026-10-15 10:00:00');

        $oneOrder = User::factory()->create();
        $this->purchase($oneOrder, 10000, PaymentStatus::Confirmed, '2026-10-01 10:00:00');

        $tooOld = User::factory()->create();
        $this->purchase($tooOld, 10000, PaymentStatus::Confirmed, '2026-02-01 10:00:00'); // > 6 mois
        $this->purchase($tooOld, 10000, PaymentStatus::Confirmed, '2026-10-01 10:00:00');

        $unpaid = User::factory()->create();
        $this->purchase($unpaid, 10000, PaymentStatus::Pending, null);
        $this->purchase($unpaid, 10000, PaymentStatus::Failed, null);

        $this->assertEquals([$regular->id], $this->ids('regularCustomers'));
    }

    public function test_inactive_customers_have_no_paid_order_for_ninety_days(): void
    {
        $active = User::factory()->create();
        $this->purchase($active, 10000, PaymentStatus::Confirmed, '2026-10-10 10:00:00');

        $lapsed = User::factory()->create();
        $this->purchase($lapsed, 10000, PaymentStatus::Confirmed, '2026-07-01 10:00:00'); // 111 jours

        $never = User::factory()->create();
        $onlyPending = User::factory()->create();
        $this->purchase($onlyPending, 10000, PaymentStatus::Pending, null);

        $this->assertEquals([$lapsed->id, $never->id, $onlyPending->id], $this->ids('inactiveCustomers'));
    }

    public function test_the_abandoned_cart_segment_uses_the_dashboard_rule(): void
    {
        $product = Product::factory()->create(['price' => 7000]);
        $abandoner = User::factory()->create();
        $cart = Cart::create(['user_id' => $abandoner->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours(30)]);

        $browsing = User::factory()->create();
        $fresh = Cart::create(['user_id' => $browsing->id]);
        CartItem::create(['cart_id' => $fresh->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->artisan('carts:detect-abandoned')->assertSuccessful();

        $this->assertEquals([$abandoner->id], $this->ids('withAbandonedCart'));
        $this->assertEquals(['Inactive', 'Panier abandonné'], $abandoner->segments());
    }

    public function test_the_list_shows_purchase_stats_from_paid_orders_only_and_filters_by_segment(): void
    {
        $regular = User::factory()->create(['name' => 'Awa Régulière']);
        $this->purchase($regular, 10000, PaymentStatus::Confirmed, '2026-09-01 10:00:00');
        $this->purchase($regular, 15000, PaymentStatus::Confirmed, '2026-10-15 10:00:00');
        $this->purchase($regular, 99000, PaymentStatus::Pending, null);
        $dormant = User::factory()->create(['name' => 'Binta Dormante']);
        $this->actingAs(Admin::factory()->create(), 'admin');

        $row = User::query()->withPurchaseStats()->find($regular->id);
        $this->assertEquals(2, $row->paid_orders_count);
        $this->assertEquals(25000, (float) $row->total_spent);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$regular, $dormant])
            ->filterTable('regular')
            ->assertCanSeeTableRecords([$regular])
            ->assertCanNotSeeTableRecords([$dormant])
            ->removeTableFilters()
            ->filterTable('inactive')
            ->assertCanSeeTableRecords([$dormant])
            ->assertCanNotSeeTableRecords([$regular]);
    }

    public function test_the_customer_page_shows_the_full_order_history_and_is_traced(): void
    {
        $customer = User::factory()->create();
        $this->purchase($customer, 10000, PaymentStatus::Confirmed, '2026-10-01 10:00:00');
        $this->purchase($customer, 20000, PaymentStatus::Pending, null);
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])
            ->assertSee('Commandes payées')
            ->assertSee('10 000 FCFA');

        Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
            ->assertCanSeeTableRecords($customer->orders);

        $activity = AdminActivity::where('action', 'customer.viewed')->sole();
        $this->assertEquals($admin->id, $activity->admin_id);
        $this->assertEquals($customer->email, $activity->subject_label);
    }

    public function test_customer_data_is_reserved_to_full_admins_and_read_only(): void
    {
        $customer = User::factory()->create();

        $this->actingAs(Admin::factory()->manager()->create(), 'admin');
        $this->get(CustomerResource::getUrl('index'))->assertForbidden();
        $this->get(CustomerResource::getUrl('view', ['record' => $customer]))->assertForbidden();
        $this->assertEquals(0, AdminActivity::where('action', 'customer.viewed')->count());

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(CustomerResource::getUrl('index'))->assertOk();
        $this->assertFalse(CustomerResource::canCreate());
        $this->assertFalse(CustomerResource::canEdit($customer));
        $this->assertFalse(CustomerResource::canDelete($customer));
    }

    public function test_customer_pages_require_admin_authentication(): void
    {
        $customer = User::factory()->create();

        $this->get(CustomerResource::getUrl('index'))->assertRedirect();
        $this->actingAs($customer)->get(CustomerResource::getUrl('view', ['record' => $customer]))->assertRedirect();
    }
}

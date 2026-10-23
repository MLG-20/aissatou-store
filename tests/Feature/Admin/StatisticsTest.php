<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\SalesOverview;
use App\Filament\Widgets\TopProducts;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Checklist phase 6 : CA exact sur les commandes payées, meilleures ventes
 * réelles, panier abandonné selon la règle, tableau de bord rapide avec des
 * milliers de commandes, statistiques invisibles sans permission.
 */
class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 15:00:00'));
    }

    private function stats(): StatisticsService
    {
        return app(StatisticsService::class);
    }

    /** Commande insérée directement, avec un paiement dans l'état voulu. */
    private function order(float $total, PaymentStatus $status, ?string $confirmedAt, array $items = [], ?string $createdAt = null): int
    {
        $user = User::factory()->create();
        $created = $createdAt ?? $confirmedAt ?? now()->toDateTimeString();

        $orderId = DB::table('orders')->insertGetId([
            'user_id' => $user->id,
            'number' => 'AS-2026-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::New->value,
            'payment_status' => $status->value,
            'subtotal' => $total,
            'discount_total' => 0,
            'shipping_total' => 0,
            'total' => $total,
            'recipient_name' => 'Test', 'phone' => '770000000', 'city' => 'Dakar', 'address_line' => 'Rue 1',
            'created_at' => $created, 'updated_at' => $created,
        ]);

        DB::table('payments')->insert([
            'order_id' => $orderId, 'provider' => 'cod', 'status' => $status->value, 'amount' => $total,
            'confirmed_at' => $confirmedAt, 'created_at' => $created, 'updated_at' => $created,
        ]);

        foreach ($items as [$name, $price, $quantity]) {
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'product_name' => $name, 'unit_price' => $price, 'quantity' => $quantity,
                'created_at' => $created, 'updated_at' => $created,
            ]);
        }

        return $orderId;
    }

    public function test_revenue_is_exactly_the_sum_of_orders_paid_in_the_period(): void
    {
        $this->order(10000, PaymentStatus::Confirmed, '2026-10-18 10:00:00');
        $this->order(25000, PaymentStatus::Confirmed, '2026-10-20 09:00:00'); // aujourd'hui (calcul direct)
        $this->order(99000, PaymentStatus::Confirmed, '2026-10-10 10:00:00'); // hors période
        $this->order(50000, PaymentStatus::Pending, null, createdAt: '2026-10-19 10:00:00');
        $this->order(60000, PaymentStatus::Failed, null, createdAt: '2026-10-19 11:00:00');
        $this->order(70000, PaymentStatus::Refunded, '2026-10-19 12:00:00');

        $summary = $this->stats()->summary(CarbonImmutable::parse('2026-10-14'), CarbonImmutable::parse('2026-10-20'))['current'];

        $expected = DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('p.status', PaymentStatus::Confirmed->value)
            ->whereBetween('p.confirmed_at', ['2026-10-14 00:00:00', '2026-10-20 23:59:59'])
            ->sum('o.total');

        $this->assertEquals(35000, $summary['revenue']);
        $this->assertEquals((float) $expected, $summary['revenue']);
        $this->assertEquals(2, $summary['paid_orders']);
        $this->assertEquals(17500, $summary['average_basket']);
    }

    public function test_cash_on_delivery_counts_on_the_delivery_date(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 15000]);
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $order = app(OrderService::class)->createFromCart($user, $cart, Address::factory()->for($user)->create(['city' => 'Nulle part']), 'cod');

        $this->assertEquals(0, $this->stats()->summary(today(), today())['current']['revenue']);

        foreach ([OrderStatus::Preparing, OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
            $order = app(OrderService::class)->changeStatus($order->fresh(), $status);
        }

        $this->assertNotNull($order->payment->fresh()->confirmed_at);
        $this->assertEquals(15000, $this->stats()->summary(today(), today())['current']['revenue']);
    }

    public function test_the_previous_period_is_the_same_length_just_before(): void
    {
        $this->order(10000, PaymentStatus::Confirmed, '2026-10-19 10:00:00'); // période courante (7 j)
        $this->order(4000, PaymentStatus::Confirmed, '2026-10-12 10:00:00');  // période précédente
        $this->order(9999, PaymentStatus::Confirmed, '2026-10-01 10:00:00');  // ni l'une ni l'autre

        $summary = $this->stats()->summary(CarbonImmutable::parse('2026-10-14'), CarbonImmutable::parse('2026-10-20'));

        $this->assertEquals(10000, $summary['current']['revenue']);
        $this->assertEquals(4000, $summary['previous']['revenue']);
    }

    public function test_top_products_come_from_paid_order_lines_only(): void
    {
        $this->order(30000, PaymentStatus::Confirmed, '2026-10-19 10:00:00', [['Voile soie', 10000, 3]]);
        $this->order(40000, PaymentStatus::Confirmed, '2026-10-19 11:00:00', [['Parfum oud', 40000, 1], ['Voile soie', 10000, 1]]);
        $this->order(90000, PaymentStatus::Pending, null, [['Sac cuir', 30000, 3]], '2026-10-19 12:00:00');

        $byQuantity = $this->stats()->topProducts(today()->subDays(6), today(), 'quantity');
        $byRevenue = $this->stats()->topProducts(today()->subDays(6), today(), 'revenue');

        $this->assertEquals(['Voile soie', 'Parfum oud'], array_column($byQuantity, 'product'));
        $this->assertEquals(4, $byQuantity[0]->quantity);
        $this->assertEquals(40000, $byQuantity[0]->revenue);
        $this->assertCount(2, $byRevenue); // le sac (non payé) n'apparaît pas
    }

    public function test_unique_visitors_are_counted_once_a_day_without_storing_the_ip(): void
    {
        $browser = ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/130'];

        $this->withHeaders($browser)->get('/')->assertOk();
        $this->withHeaders($browser)->get('/catalogue')->assertOk(); // même visiteur, même jour
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone) Safari/17'])->get('/')->assertOk();
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/')->assertOk();
        $this->withHeaders($browser + ['X-Requested-With' => 'XMLHttpRequest'])->get('/catalogue');

        $this->assertEquals(2, DB::table('daily_visitors')->count());
        $this->assertFalse(DB::table('daily_visitors')->where('fingerprint', 'like', '%127.0.0.1%')->exists());
        $this->assertEquals(64, strlen(DB::table('daily_visitors')->value('fingerprint')));
    }

    public function test_admins_browsing_the_shop_are_not_counted(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 Chrome/130'])
            ->get('/')->assertOk();

        $this->assertEquals(0, DB::table('daily_visitors')->count());
    }

    public function test_conversion_is_orders_placed_over_unique_visitors(): void
    {
        DB::table('daily_visitors')->insert(collect(range(1, 40))->map(fn ($i) => ['day' => '2026-10-19', 'fingerprint' => hash('sha256', (string) $i)])->all());
        $this->order(10000, PaymentStatus::Confirmed, '2026-10-19 10:00:00');
        $this->order(10000, PaymentStatus::Pending, null, createdAt: '2026-10-19 11:00:00');

        $summary = $this->stats()->summary(CarbonImmutable::parse('2026-10-19'), CarbonImmutable::parse('2026-10-19'))['current'];

        $this->assertEquals(2, $summary['placed_orders']);
        $this->assertEquals(40, $summary['visitors']);
        $this->assertEquals(5.0, $summary['conversion']);
    }

    public function test_abandoned_carts_follow_the_documented_rule(): void
    {
        $product = Product::factory()->create(['price' => 5000]);
        $make = function (?User $user, int $hoursAgo, bool $withItem = true) use ($product): Cart {
            $cart = Cart::create(['user_id' => $user?->id, 'session_id' => $user ? null : Str::random(40)]);
            if ($withItem) {
                CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
            }
            DB::table('carts')->where('id', $cart->id)->update(['updated_at' => now()->subHours($hoursAgo)]);

            return $cart;
        };

        $old = $make(User::factory()->create(), 30);
        $guest = $make(null, 48);
        $recent = $make(User::factory()->create(), 5);
        $empty = $make(User::factory()->create(), 30, withItem: false);
        $buyer = User::factory()->create();
        $orderedSince = $make($buyer, 30);
        $this->order(1000, PaymentStatus::Pending, null, createdAt: now()->subHours(2)->toDateTimeString());
        DB::table('orders')->latest('id')->limit(1)->update(['user_id' => $buyer->id]);

        $this->assertEquals(2, $this->stats()->detectAbandonedCarts());

        $this->assertNotNull($old->fresh()->abandoned_at);
        $this->assertNotNull($guest->fresh()->abandoned_at);
        $this->assertNull($recent->fresh()->abandoned_at);
        $this->assertNull($empty->fresh()->abandoned_at);
        $this->assertNull($orderedSince->fresh()->abandoned_at);
        $this->assertEquals(['count' => 2, 'value' => 20000.0, 'customers' => 1], $this->stats()->abandonedCarts());

        // Le client revient et modifie son panier : il n'est plus abandonné.
        $old->items()->first()->update(['quantity' => 3]);
        $this->assertNull($old->fresh()->abandoned_at);
        $this->assertEquals(0, $this->stats()->detectAbandonedCarts()); // pas re-marqué avant 24 h
    }

    public function test_daily_aggregates_are_stored_idempotent_and_self_healing(): void
    {
        $this->order(10000, PaymentStatus::Confirmed, '2026-10-18 10:00:00');

        $this->artisan('stats:aggregate', ['--days' => 3])->assertSuccessful();
        $this->artisan('stats:aggregate', ['--days' => 3])->assertSuccessful();

        $this->assertEquals(3, DB::table('daily_stats')->count());
        $this->assertEquals(10000, (float) DB::table('daily_stats')->where('day', '2026-10-18')->value('revenue'));

        // Jour jamais agrégé (tâche non passée) : calculé à la lecture.
        $this->order(5000, PaymentStatus::Confirmed, '2026-10-05 10:00:00');
        $summary = $this->stats()->summary(CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'));
        $this->assertEquals(5000, $summary['current']['revenue']);
        $this->assertTrue(DB::table('daily_stats')->where('day', '2026-10-05')->exists());
    }

    public function test_old_visitor_fingerprints_are_purged(): void
    {
        DB::table('daily_visitors')->insert([
            ['day' => '2026-09-01', 'fingerprint' => str_repeat('a', 64)],
            ['day' => '2026-10-19', 'fingerprint' => str_repeat('b', 64)],
        ]);

        $this->artisan('stats:aggregate', ['--days' => 1])->assertSuccessful();

        $this->assertEquals(['2026-10-19'], DB::table('daily_visitors')->pluck('day')->map(fn ($d) => substr((string) $d, 0, 10))->all());
    }

    public function test_the_dashboard_stays_fast_with_thousands_of_orders(): void
    {
        $user = User::factory()->create();
        $start = CarbonImmutable::parse('2026-01-01');
        $orders = [];
        $count = 3000;

        for ($i = 0; $i < $count; $i++) {
            $at = $start->addMinutes($i * 137)->toDateTimeString(); // réparties sur ~9 mois
            $orders[] = [
                'user_id' => $user->id, 'number' => 'AS-VOL-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'status' => OrderStatus::Delivered->value, 'payment_status' => PaymentStatus::Confirmed->value,
                'subtotal' => 10000, 'discount_total' => 0, 'shipping_total' => 0, 'total' => 10000,
                'recipient_name' => 'V', 'phone' => '0', 'city' => 'Dakar', 'address_line' => 'R',
                'created_at' => $at, 'updated_at' => $at,
            ];
        }

        foreach (array_chunk($orders, 500) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        DB::statement("INSERT INTO payments (order_id, provider, status, amount, confirmed_at, created_at, updated_at)
            SELECT id, 'cod', 'confirme', total, created_at, created_at, created_at FROM orders WHERE number LIKE 'AS-VOL-%'");
        DB::statement("INSERT INTO order_items (order_id, product_name, unit_price, quantity, created_at, updated_at)
            SELECT id, CONCAT('Produit ', MOD(id, 40)), 10000, 1, created_at, created_at FROM orders WHERE number LIKE 'AS-VOL-%'");

        $this->artisan('stats:aggregate', ['--from' => '2026-01-01'])->assertSuccessful();
        $this->actingAs(Admin::factory()->create(), 'admin');

        DB::enableQueryLog();
        $began = microtime(true);

        Livewire::test(SalesOverview::class, ['filters' => ['period' => 'month']])->assertOk();
        Livewire::test(RevenueChart::class)->assertOk();
        Livewire::test(TopProducts::class, ['filters' => ['period' => 'month']])->assertOk();

        $elapsed = microtime(true) - $began;
        $queries = count(DB::getQueryLog());

        // Lecture des agrégats : le nombre de requêtes ne dépend pas du volume.
        $this->assertLessThan(40, $queries, "{$queries} requêtes pour afficher le tableau de bord");
        $this->assertLessThan(2.0, $elapsed, 'Tableau de bord trop lent : '.round($elapsed, 2).' s');

        // Et les chiffres restent exacts sur ce volume.
        $expected = DB::table('payments')->where('status', 'confirme')
            ->whereBetween('confirmed_at', [today()->subDays(29)->startOfDay(), today()->endOfDay()])
            ->count() * 10000;
        $this->assertEquals($expected, $this->stats()->summary(today()->subDays(29), today())['current']['revenue']);
    }

    public function test_statistics_are_invisible_without_the_permission(): void
    {
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        $this->get(Dashboard::getUrl())->assertOk()->assertDontSee('Chiffre d\'affaires');
        $this->assertFalse(SalesOverview::canView());
        $this->assertFalse(RevenueChart::canView());
        $this->assertFalse(TopProducts::canView());

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->assertTrue(SalesOverview::canView());
        Livewire::test(SalesOverview::class)->assertSee('Chiffre d\'affaires');
    }

    public function test_statistics_require_admin_authentication(): void
    {
        $this->get(Dashboard::getUrl())->assertRedirect();
        $this->actingAs(User::factory()->create())->get(Dashboard::getUrl())->assertRedirect();
    }
}

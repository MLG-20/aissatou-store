<?php

namespace Tests\Feature\Marketing;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\Content\SaleSourceResource\Pages\ManageSaleSources;
use App\Filament\Widgets\SalesBySource;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Content\SaleSource;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\Marketing\SaleSourceTracker;
use App\Services\StatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Provenance des ventes (Visibilité V4) : du lien de suivi à la commande,
 * jusqu'au bloc « Ventes par source » du tableau de bord.
 */
class SaleSourceTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    private const BROWSER = 'Mozilla/5.0 (iPhone) Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Un test sans transaction peut avoir laissé une visite en base.
        DB::table('daily_visitors')->delete();
        $this->withHeader('User-Agent', self::BROWSER);
    }

    private function sourceId(string $code): int
    {
        return SaleSource::query()->where('code', $code)->value('id');
    }

    public function test_a_tracking_link_remembers_the_source(): void
    {
        $this->get('/?src=tiktok')->assertOk();

        $this->assertSame($this->sourceId('tiktok'), session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_utm_source_is_accepted_and_case_does_not_matter(): void
    {
        $this->get('/?utm_source=Instagram')->assertOk();

        $this->assertSame($this->sourceId('instagram'), session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_a_later_tracking_link_replaces_the_source(): void
    {
        $this->get('/?src=tiktok');
        $this->get('/?src=instagram');

        $this->assertSame($this->sourceId('instagram'), session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_unknown_malformed_or_inactive_codes_are_ignored(): void
    {
        SaleSource::query()->where('code', 'facebook')->update(['is_active' => false]);

        $this->get('/?src=inconnu')->assertOk();
        $this->get('/?src[]=tiktok')->assertOk();
        $this->get('/?src=facebook')->assertOk();

        $this->assertNull(session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_the_referrer_is_used_without_a_tracking_link(): void
    {
        $this->withHeader('Referer', 'https://l.instagram.com/?u=x')->get('/')->assertOk();

        $this->assertSame($this->sourceId('instagram'), session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_google_is_recognised_on_a_national_domain(): void
    {
        $this->withHeader('Referer', 'https://www.google.sn/')->get('/')->assertOk();

        $this->assertSame($this->sourceId('google'), session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_the_referrer_never_replaces_a_tracking_link(): void
    {
        $this->get('/?src=whatsapp');
        $this->withHeader('Referer', 'https://www.tiktok.com/')->get('/');

        $this->assertSame($this->sourceId('whatsapp'), session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_a_lookalike_domain_is_not_recognised(): void
    {
        $this->withHeader('Referer', 'https://notinstagram.com/')->get('/')->assertOk();

        $this->assertNull(session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_robots_are_ignored(): void
    {
        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')->get('/?src=tiktok')->assertOk();

        $this->assertNull(session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_the_order_keeps_the_source(): void
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create(['is_default' => true]);

        $this->get('/?src=tiktok');
        $this->actingAs($user)->post(route('shop.checkout.store'), ['address_id' => $address->id, 'provider' => 'cod']);

        $this->assertSame($this->sourceId('tiktok'), Order::query()->sole()->sale_source_id);
    }

    public function test_the_order_has_no_source_for_a_direct_visit(): void
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create(['is_default' => true]);

        $this->actingAs($user)->post(route('shop.checkout.store'), ['address_id' => $address->id, 'provider' => 'cod']);

        $this->assertNull(Order::query()->sole()->sale_source_id);
    }

    public function test_the_visit_is_counted_for_the_source(): void
    {
        $this->get('/?src=tiktok')->assertOk();

        $this->assertSame($this->sourceId('tiktok'), DB::table('daily_visitors')->sole()->sale_source_id);
    }

    public function test_a_direct_visitor_arriving_later_by_a_link_is_completed_not_counted_twice(): void
    {
        $this->get('/')->assertOk();
        $this->get('/?src=tiktok')->assertOk();

        $this->assertSame($this->sourceId('tiktok'), DB::table('daily_visitors')->sole()->sale_source_id);
    }

    public function test_sales_by_source_match_the_dashboard_totals(): void
    {
        $tiktok = $this->sourceId('tiktok');
        $today = now();

        $this->paidOrder(20000, $tiktok);
        $this->paidOrder(5000, null);
        $this->unpaidOrder($tiktok);
        DB::table('daily_visitors')->insert([
            ['day' => $today->toDateString(), 'fingerprint' => str_repeat('a', 64), 'sale_source_id' => $tiktok],
            ['day' => $today->toDateString(), 'fingerprint' => str_repeat('b', 64), 'sale_source_id' => null],
            ['day' => $today->toDateString(), 'fingerprint' => str_repeat('c', 64), 'sale_source_id' => null],
        ]);

        $statistics = app(StatisticsService::class);
        $rows = $statistics->salesBySource($today, $today);
        $totals = $statistics->summary($today, $today)['current'];

        $this->assertSame(['TikTok', 'Non renseignée'], array_column($rows, 'source'));
        $this->assertSame([1, 2], array_column($rows, 'visitors'));
        $this->assertSame([2, 1], array_column($rows, 'placed_orders'));
        $this->assertSame([1, 1], array_column($rows, 'paid_orders'));
        $this->assertSame([20000.0, 5000.0], array_column($rows, 'revenue'));

        $this->assertEquals($totals['revenue'], array_sum(array_column($rows, 'revenue')));
        $this->assertEquals($totals['placed_orders'], array_sum(array_column($rows, 'placed_orders')));
        $this->assertEquals($totals['visitors'], array_sum(array_column($rows, 'visitors')));
    }

    public function test_the_widget_requires_the_statistics_permission(): void
    {
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');
        $this->assertFalse(SalesBySource::canView());

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->assertTrue(SalesBySource::canView());
        Livewire::test(SalesBySource::class)->assertSee('Ventes par source');
    }

    public function test_the_admin_can_add_a_source_and_copy_its_link(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ManageSaleSources::class)
            ->callAction('create', ['name' => 'Influenceuse Awa', 'code' => 'influenceuse-awa', 'is_active' => true])
            ->assertHasNoActionErrors()
            ->assertSee(url('/').'/?src=influenceuse-awa');

        // L'équipe connectée n'est pas suivie : le lien se teste en visiteur.
        Auth::guard('admin')->logout();

        $this->get('/?src=influenceuse-awa')->assertOk();
        $this->assertSame($this->sourceId('influenceuse-awa'), session(SaleSourceTracker::SESSION_KEY));
    }

    public function test_a_code_must_be_clean_and_unique(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ManageSaleSources::class)
            ->callAction('create', ['name' => 'Doublon', 'code' => 'tiktok', 'is_active' => true])
            ->assertHasActionErrors(['code' => 'unique'])
            ->callAction('create', ['name' => 'Accent', 'code' => 'Été 2026', 'is_active' => true])
            ->assertHasActionErrors(['code' => 'regex']);
    }

    private function unpaidOrder(?int $source): void
    {
        $this->order(9000, $source, PaymentStatus::Pending, null);
    }

    private function paidOrder(float $total, ?int $source): void
    {
        $this->order($total, $source, PaymentStatus::Confirmed, now());
    }

    /** Commande insérée directement, avec un paiement dans l'état voulu. */
    private function order(float $total, ?int $source, PaymentStatus $status, mixed $confirmedAt): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'number' => 'AS-2026-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::New->value,
            'payment_status' => $status->value,
            'subtotal' => $total,
            'discount_total' => 0,
            'shipping_total' => 0,
            'total' => $total,
            'recipient_name' => 'Test', 'phone' => '770000000', 'city' => 'Dakar', 'address_line' => 'Rue 1',
            'sale_source_id' => $source,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('payments')->insert([
            'order_id' => $orderId, 'provider' => 'cod', 'status' => $status->value, 'amount' => $total,
            'confirmed_at' => $confirmedAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

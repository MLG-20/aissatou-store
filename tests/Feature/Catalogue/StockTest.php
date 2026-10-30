<?php

namespace Tests\Feature\Catalogue;

use App\Enums\Catalogue\StockMovementType;
use App\Enums\Order\OrderStatus;
use App\Exceptions\OrderException;
use App\Filament\Clusters\Stock;
use App\Filament\Resources\Catalogue\InventoryResource;
use App\Filament\Resources\Catalogue\InventoryResource\Pages\ListInventory;
use App\Filament\Resources\Catalogue\InventoryResource\Pages\ViewInventory;
use App\Filament\Resources\Catalogue\InventoryResource\Widgets\StockByCategory;
use App\Filament\Resources\Catalogue\ProductResource\Pages\EditProduct;
use App\Filament\Resources\Catalogue\StockMovementResource;
use App\Filament\Resources\Catalogue\StockMovementResource\Pages\ListStockMovements;
use App\Mail\LowStockAlert;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Catalogue\StockMovement;
use App\Models\Content\SiteSetting;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Checklist phase 6 : « chaque ajustement manuel de stock est journalisé
 * avec l'auteur, la date et la raison » et « les alertes de stock faible se
 * déclenchent correctement selon le seuil configuré ».
 */
class StockTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        SiteSetting::current()->update(['low_stock_threshold' => 3]);
    }

    private function variant(int $stock, ?int $threshold = null): ProductVariant
    {
        $product = Product::factory()->create(['price' => 10000, 'is_active' => true]);

        return ProductVariant::factory()->for($product)->create([
            'stock' => $stock,
            'low_stock_threshold' => $threshold,
            'is_active' => true,
            'price_override' => null,
        ]);
    }

    private function buy(ProductVariant $variant, int $quantity, ?User $user = null): Order
    {
        $user ??= User::factory()->create();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);

        return app(OrderService::class)->createFromCart($user, $cart, Address::factory()->for($user)->create(), 'cod');
    }

    public function test_the_initial_stock_is_the_first_movement(): void
    {
        $variant = $this->variant(10);

        $movement = $variant->movements()->sole();
        $this->assertEquals(StockMovementType::Initial, $movement->type);
        $this->assertEquals(10, $movement->quantity);
        $this->assertEquals(10, $movement->stock_after);
    }

    public function test_a_sale_is_journaled_with_its_order(): void
    {
        $variant = $this->variant(10);

        $order = $this->buy($variant, 2);

        $sale = $variant->movements()->where('type', StockMovementType::Sale)->sole();
        $this->assertEquals(-2, $sale->quantity);
        $this->assertEquals(8, $sale->stock_after);
        $this->assertEquals($order->id, $sale->order_id);
        $this->assertNull($sale->admin_id);
        $this->assertEquals(8, $variant->fresh()->stock);
    }

    public function test_a_cancellation_restores_stock_with_its_author(): void
    {
        $variant = $this->variant(10);
        $order = $this->buy($variant, 2);
        $admin = Admin::factory()->create();

        app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled, $admin->id);

        $back = $variant->movements()->where('type', StockMovementType::Cancellation)->sole();
        $this->assertEquals(2, $back->quantity);
        $this->assertEquals(10, $back->stock_after);
        $this->assertEquals($admin->id, $back->admin_id);
        $this->assertEquals($admin->name, $back->admin_name);
    }

    public function test_stock_cannot_be_changed_outside_the_service(): void
    {
        $variant = $this->variant(10);

        $this->expectException(LogicException::class);

        $variant->update(['stock' => 50]);
    }

    public function test_a_manual_adjustment_records_author_date_and_reason(): void
    {
        $variant = $this->variant(10);
        $admin = Admin::factory()->manager()->create();

        $movement = app(StockService::class)->adjust($variant, 14, 'Réassort fournisseur', $admin);

        $this->assertEquals(StockMovementType::Adjustment, $movement->type);
        $this->assertEquals(4, $movement->quantity);
        $this->assertEquals(14, $movement->stock_after);
        $this->assertEquals('Réassort fournisseur', $movement->reason);
        $this->assertEquals($admin->id, $movement->admin_id);
        $this->assertNotNull($movement->created_at);
        $this->assertEquals(14, $variant->fresh()->stock);
    }

    public function test_an_adjustment_without_reason_or_negative_is_refused(): void
    {
        $variant = $this->variant(10);
        $admin = Admin::factory()->create();

        foreach ([[12, '  '], [-1, 'Inventaire'], [10, 'Aucun changement']] as [$stock, $reason]) {
            try {
                app(StockService::class)->adjust($variant, $stock, $reason, $admin);
                $this->fail("Ajustement accepté à tort ({$stock}, « {$reason} »).");
            } catch (ValidationException) {
                // attendu
            }
        }

        $this->assertEquals(10, $variant->fresh()->stock);
        $this->assertEquals(0, $variant->movements()->where('type', StockMovementType::Adjustment)->count());
    }

    public function test_the_movement_log_is_immutable(): void
    {
        $movement = $this->variant(5)->movements()->sole();

        $this->expectException(LogicException::class);

        $movement->update(['quantity' => 999]);
    }

    public function test_the_inventory_adjust_action_traces_the_logged_in_manager(): void
    {
        $variant = $this->variant(10);
        $manager = Admin::factory()->manager()->create();
        $this->actingAs($manager, 'admin');

        Livewire::test(ListInventory::class)
            ->callTableAction('adjust', $variant, data: ['stock' => 7, 'reason' => 'Article abîmé en boutique'])
            ->assertHasNoTableActionErrors();

        $movement = StockMovement::where('type', StockMovementType::Adjustment)->sole();
        $this->assertEquals($manager->id, $movement->admin_id);
        $this->assertEquals('Article abîmé en boutique', $movement->reason);
        $this->assertEquals(7, $variant->fresh()->stock);
    }

    public function test_the_inventory_adjust_action_requires_a_reason(): void
    {
        $variant = $this->variant(10);
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ListInventory::class)
            ->callTableAction('adjust', $variant, data: ['stock' => 7, 'reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        $this->assertEquals(10, $variant->fresh()->stock);
    }

    public function test_editing_a_product_never_changes_an_existing_variants_stock(): void
    {
        $variant = $this->variant(10);
        $this->actingAs(Admin::factory()->create(), 'admin');

        $component = Livewire::test(EditProduct::class, ['record' => $variant->product->getRouteKey()]);
        $key = array_key_first($component->get('data.variants'));

        $component
            ->set("data.variants.{$key}.stock", 999)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(10, $variant->fresh()->stock);
    }

    public function test_crossing_the_threshold_alerts_stock_admins_once(): void
    {
        $variant = $this->variant(6);
        $manager = Admin::factory()->manager()->create();
        $noAccess = Admin::factory()->withoutRole()->create();

        $this->buy($variant, 2); // 4 : au-dessus du seuil (3)
        Mail::assertNotQueued(LowStockAlert::class);

        $this->buy($variant, 1); // 3 : seuil atteint → alerte
        Mail::assertQueued(LowStockAlert::class, 1);
        Mail::assertQueued(LowStockAlert::class, fn (LowStockAlert $mail) => $mail->hasTo($manager->email) && ! $mail->hasTo($noAccess->email));

        $this->buy($variant, 1); // 2 : toujours sous le seuil → pas de nouvelle alerte
        Mail::assertQueued(LowStockAlert::class, 1);
    }

    public function test_the_alert_rearms_once_stock_goes_back_above_the_threshold(): void
    {
        $variant = $this->variant(4);
        $admin = Admin::factory()->create();

        $this->buy($variant, 1); // 3 → alerte
        app(StockService::class)->adjust($variant, 10, 'Réassort', $admin); // réarmée
        $this->assertNull($variant->fresh()->low_stock_notified_at);

        app(StockService::class)->adjust($variant, 1, 'Casse', $admin); // repasse sous le seuil → nouvelle alerte

        Mail::assertQueued(LowStockAlert::class, 2);
    }

    public function test_a_variant_threshold_overrides_the_global_one(): void
    {
        $variant = $this->variant(12, threshold: 10);
        Admin::factory()->create(); // destinataire de l'alerte

        $this->buy($variant, 2); // 10 : seuil propre atteint (le global 3 ne l'aurait pas déclenché)

        Mail::assertQueued(LowStockAlert::class, 1);
        $this->assertTrue($variant->fresh()->isLowStock());
    }

    public function test_a_failed_order_leaves_no_movement_and_no_alert(): void
    {
        $low = $this->variant(4);
        $scarce = $this->variant(1);
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $low->product_id, 'product_variant_id' => $low->id, 'quantity' => 2]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $scarce->product_id, 'product_variant_id' => $scarce->id, 'quantity' => 5]);

        try {
            app(OrderService::class)->createFromCart($user, $cart, Address::factory()->for($user)->create(), 'cod');
            $this->fail('La commande aurait dû échouer (stock insuffisant).');
        } catch (OrderException) {
            // attendu
        }

        $this->assertEquals(4, $low->fresh()->stock);
        $this->assertEquals(0, StockMovement::where('type', StockMovementType::Sale)->count());
        Mail::assertNotQueued(LowStockAlert::class);
    }

    public function test_the_inventory_badge_counts_low_or_out_of_stock_sellable_variants(): void
    {
        $this->variant(10);
        $this->variant(2);
        $this->variant(0);
        $inactive = $this->variant(0);
        $inactive->update(['is_active' => false]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        $this->assertEquals('2', InventoryResource::getNavigationBadge());
    }

    public function test_the_category_summary_aggregates_sellable_variants(): void
    {
        $category = Category::factory()->create(['name' => 'Foulards']);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        ProductVariant::factory()->for($product)->create(['stock' => 10, 'is_active' => true]);
        ProductVariant::factory()->for($product)->create(['stock' => 2, 'is_active' => true]);
        ProductVariant::factory()->for($product)->create(['stock' => 0, 'is_active' => true]);
        $this->actingAs(Admin::factory()->create(), 'admin');

        $row = collect((new StockByCategory)->rows())->firstWhere('category', 'Foulards');

        $this->assertEquals(3, $row->variants);
        $this->assertEquals(12, $row->units);
        $this->assertEquals(1, $row->low);
        $this->assertEquals(1, $row->out);
    }

    public function test_stock_pages_are_open_to_managers_and_closed_without_permission(): void
    {
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');
        $this->get(InventoryResource::getUrl('index'))->assertOk();
        $this->get(StockMovementResource::getUrl('index'))->assertOk();
        // Widget chargé en différé par Filament : testé directement.
        Livewire::test(StockByCategory::class)->assertSee('Vue d\'ensemble par catégorie');
        $this->assertTrue(StockByCategory::canView());

        $this->actingAs(Admin::factory()->withoutRole()->create(), 'admin');
        $this->get(InventoryResource::getUrl('index'))->assertForbidden();
        $this->get(StockMovementResource::getUrl('index'))->assertForbidden();
        $this->assertFalse(StockByCategory::canView());
    }

    public function test_each_variant_has_a_sheet_with_its_stock_history(): void
    {
        $variant = $this->variant(10, threshold: 4);
        $order = $this->buy($variant, 2);
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        Livewire::test(ListInventory::class)
            ->assertCanSeeTableRecords([$variant])
            ->assertTableActionExists('view')
            ->assertTableActionDoesNotExist('history');

        Livewire::test(ViewInventory::class, ['record' => $variant->getRouteKey()])
            // Relue en base, comme la page (la base réordonne les clés JSON).
            ->assertSee($variant->fresh()->label())
            ->assertSee($variant->sku)
            ->assertSee('4 (propre à cet article)')
            ->assertSee('Historique du stock')
            ->assertSee('Stock initial')
            ->assertSee('Vente')
            ->assertSee($order->number);
    }

    public function test_the_stock_can_be_adjusted_from_the_variant_sheet(): void
    {
        $variant = $this->variant(10);
        $manager = Admin::factory()->manager()->create();
        $this->actingAs($manager, 'admin');

        Livewire::test(ViewInventory::class, ['record' => $variant->getRouteKey()])
            ->callAction('adjust', data: ['stock' => 12, 'reason' => 'Réassort fournisseur'])
            ->assertHasNoActionErrors();

        $movement = StockMovement::where('type', StockMovementType::Adjustment)->sole();
        $this->assertEquals($manager->id, $movement->admin_id);
        $this->assertEquals(12, $variant->fresh()->stock);
    }

    public function test_a_movement_line_opens_the_variant_sheet(): void
    {
        $variant = $this->variant(10);
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        Livewire::test(ListStockMovements::class)
            ->assertCanSeeTableRecords($variant->movements)
            ->assertSee(InventoryResource::getUrl('view', ['record' => $variant]), false);
    }

    public function test_old_stock_links_and_the_alert_email_lead_to_the_new_pages(): void
    {
        $variant = $this->variant(2);
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        $this->get('/admin/inventaire')->assertRedirect(InventoryResource::getUrl('index'));
        $this->get('/admin/mouvements-de-stock')->assertRedirect(StockMovementResource::getUrl('index'));

        (new LowStockAlert($variant))->assertSeeInHtml(InventoryResource::getUrl('view', ['record' => $variant], panel: 'admin'));
        $this->assertEquals(InventoryResource::getNavigationBadge(), Stock::getNavigationBadge());
    }
}

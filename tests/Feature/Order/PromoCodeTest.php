<?php

namespace Tests\Feature\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PromoCodeType;
use App\Exceptions\PromoCodeException;
use App\Filament\Resources\Order\PromoCodeResource;
use App\Filament\Resources\Order\PromoCodeResource\Pages\CreatePromoCode;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Delivery\DeliveryZone;
use App\Models\Order\Order;
use App\Models\Order\PromoCode;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PromoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Checklist phase 6 : « un code promo expiré ou ayant atteint son nombre
 * maximum d'utilisations est refusé au moment de la validation finale, pas
 * seulement à la saisie » + règles d'application et cas limites (§5).
 * La concurrence réelle (deux processus) est dans PromoCodeConcurrencyTest.
 */
class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function promo(array $attributes = []): PromoCode
    {
        return PromoCode::create($attributes + [
            'code' => 'BIENVENUE',
            'type' => PromoCodeType::Percent,
            'value' => 10,
            'is_active' => true,
        ]);
    }

    /** @return array{0: User, 1: Cart, 2: Address} */
    private function cartFor(array $products): array
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);

        foreach ($products as [$product, $quantity]) {
            CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity]);
        }

        return [$user, $cart, Address::factory()->for($user)->create(['city' => 'Ville sans zone'])];
    }

    private function order(User $user, Cart $cart, Address $address, ?string $code): Order
    {
        return app(OrderService::class)->createFromCart($user, $cart, $address, 'cod', $code);
    }

    public function test_a_percentage_code_discounts_the_articles_and_is_frozen_on_the_order(): void
    {
        $promo = $this->promo();
        [$user, $cart, $address] = $this->cartFor([[Product::factory()->create(['price' => 12500]), 2]]);

        $order = $this->order($user, $cart, $address, 'bienvenue'); // casse indifférente

        $this->assertEquals(25000, (float) $order->subtotal);
        $this->assertEquals(2500, (float) $order->discount_total);
        $this->assertEquals('BIENVENUE', $order->promo_code);
        $this->assertEquals(22500, (float) $order->total);
        // Le montant envoyé au paiement est le total remisé, calculé serveur.
        $this->assertEquals(22500, (float) $order->payment->amount);
        $this->assertEquals(1, $promo->fresh()->uses_count);
        $this->assertDatabaseHas('promo_code_redemptions', ['order_id' => $order->id, 'user_id' => $user->id]);
    }

    public function test_the_discount_never_applies_to_shipping(): void
    {
        $this->promo(['type' => PromoCodeType::Fixed, 'value' => 50000]);
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create(['price' => 8000])->id, 'quantity' => 1]);
        DeliveryZone::create(['name' => 'Dakar', 'city' => 'Dakar', 'fee' => 1000, 'is_active' => true]);
        $address = Address::factory()->for($user)->create(['city' => 'Dakar']);

        $order = $this->order($user, $cart, $address, 'BIENVENUE');

        // Montant fixe plafonné aux articles : la livraison reste due.
        $this->assertEquals(8000, (float) $order->discount_total);
        $this->assertEquals(1000, (float) $order->total);
    }

    public function test_a_targeted_code_only_discounts_eligible_lines_including_subcategories(): void
    {
        $parent = Category::factory()->create(['name' => 'Foulards']);
        $child = Category::factory()->create(['name' => 'Foulards soie', 'parent_id' => $parent->id]);
        $other = Category::factory()->create(['name' => 'Parfums']);
        $promo = $this->promo(['value' => 20]);
        $promo->categories()->attach($parent);

        [$user, $cart, $address] = $this->cartFor([
            [Product::factory()->create(['price' => 10000, 'category_id' => $child->id]), 1],
            [Product::factory()->create(['price' => 30000, 'category_id' => $other->id]), 1],
        ]);

        $order = $this->order($user, $cart, $address, 'BIENVENUE');

        $this->assertEquals(2000, (float) $order->discount_total); // 20 % de 10 000 seulement
    }

    public function test_a_targeted_code_with_no_eligible_article_is_refused(): void
    {
        $promo = $this->promo();
        $promo->products()->attach(Product::factory()->create());
        [$user, $cart] = $this->cartFor([[Product::factory()->create(), 1]]);

        $this->expectExceptionObject(PromoCodeException::notApplicable());

        app(PromoService::class)->quote('BIENVENUE', $user, PromoService::linesFromCart($cart));
    }

    public function test_unknown_and_inactive_codes_give_the_same_message(): void
    {
        $this->promo(['code' => 'ANCIEN', 'is_active' => false]);
        [$user, $cart] = $this->cartFor([[Product::factory()->create(), 1]]);
        $lines = PromoService::linesFromCart($cart);

        foreach (['INCONNU', 'ANCIEN'] as $code) {
            try {
                app(PromoService::class)->quote($code, $user, $lines);
                $this->fail("Code {$code} accepté à tort.");
            } catch (PromoCodeException $exception) {
                $this->assertEquals('Ce code promo n\'est pas valide.', $exception->getMessage());
            }
        }
    }

    public function test_a_code_not_started_is_refused(): void
    {
        $this->promo(['starts_at' => now()->addDay()]);
        [$user, $cart] = $this->cartFor([[Product::factory()->create(), 1]]);

        $this->expectExceptionObject(PromoCodeException::notStarted());

        app(PromoService::class)->quote('BIENVENUE', $user, PromoService::linesFromCart($cart));
    }

    public function test_a_code_that_expires_after_entry_is_refused_at_final_validation(): void
    {
        $promo = $this->promo(['ends_at' => now()->addMinutes(10)]);
        [$user, $cart, $address] = $this->cartFor([[Product::factory()->create(['price' => 10000]), 1]]);

        $this->actingAs($user)->post(route('shop.checkout.promo.apply'), ['promo_code' => 'BIENVENUE'])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('shop.checkout'))->assertSee('-1 000 FCFA');

        $this->travel(11)->minutes();

        $this->actingAs($user)->from(route('shop.checkout'))->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'cod',
        ])->assertSessionHasErrors(['promo_code' => 'Ce code promo a expiré.']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(0, $promo->fresh()->uses_count);
        $this->assertNull(session('checkout.promo_code'));
    }

    public function test_a_code_exhausted_after_entry_is_refused_at_final_validation(): void
    {
        $promo = $this->promo(['max_uses' => 1]);
        [$user, $cart, $address] = $this->cartFor([[Product::factory()->create(), 1]]);

        $this->actingAs($user)->post(route('shop.checkout.promo.apply'), ['promo_code' => 'BIENVENUE'])
            ->assertSessionHasNoErrors();

        // Une autre cliente utilise la dernière utilisation entre-temps.
        [$other, $otherCart, $otherAddress] = $this->cartFor([[Product::factory()->create(), 1]]);
        $this->order($other, $otherCart, $otherAddress, 'BIENVENUE');

        $this->actingAs($user)->from(route('shop.checkout'))->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'cod',
        ])->assertSessionHasErrors('promo_code');

        $this->assertEquals(1, Order::count());
        $this->assertEquals(1, $promo->fresh()->uses_count);
    }

    public function test_shrinking_the_cart_below_the_minimum_after_entry_is_caught(): void
    {
        $this->promo(['min_subtotal' => 20000]);
        $expensive = Product::factory()->create(['price' => 15000]);
        [$user, $cart, $address] = $this->cartFor([[$expensive, 2]]);

        $this->actingAs($user)->post(route('shop.checkout.promo.apply'), ['promo_code' => 'BIENVENUE'])
            ->assertSessionHasNoErrors();

        // Contournement tenté : le panier est réduit APRÈS validation du code.
        $cart->items()->update(['quantity' => 1]);

        $this->actingAs($user)->from(route('shop.checkout'))->post(route('shop.checkout.store'), [
            'address_id' => $address->id,
            'provider' => 'cod',
        ])->assertSessionHasErrors('promo_code');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_once_per_customer_code_cannot_be_reused_unless_the_order_was_cancelled(): void
    {
        $this->promo(['once_per_customer' => true]);
        $product = Product::factory()->create();
        [$user, $cart, $address] = $this->cartFor([[$product, 1]]);
        $first = $this->order($user, $cart, $address, 'BIENVENUE');

        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        try {
            $this->order($user, $cart, $address, 'BIENVENUE');
            $this->fail('Second usage accepté à tort.');
        } catch (PromoCodeException $exception) {
            $this->assertEquals(PromoCodeException::alreadyUsed()->getMessage(), $exception->getMessage());
        }

        // Annulée : l'utilisation est rendue, le code redevient utilisable.
        app(OrderService::class)->changeStatus($first, OrderStatus::Cancelled);
        $second = $this->order($user, $cart, $address, 'BIENVENUE');

        $this->assertEquals('BIENVENUE', $second->promo_code);
    }

    public function test_cancelling_an_order_gives_its_use_back_only_once(): void
    {
        $promo = $this->promo(['max_uses' => 1]);
        [$user, $cart, $address] = $this->cartFor([[Product::factory()->create(), 1]]);
        $order = $this->order($user, $cart, $address, 'BIENVENUE');
        $this->assertEquals('Épuisé', $promo->fresh()->statusLabel());

        app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled);
        app(PromoService::class)->release($order); // second appel : sans effet

        $this->assertEquals(0, $promo->fresh()->uses_count);
        $this->assertNotNull(DB::table('promo_code_redemptions')->where('order_id', $order->id)->value('released_at'));
    }

    public function test_the_atomic_counter_refuses_even_if_a_stale_count_was_read(): void
    {
        // Simule une course perdue : juste après la lecture verrouillée du
        // code, une autre transaction « consomme » la dernière utilisation.
        // L'évaluation a vu 0/1, mais l'UPDATE conditionnel doit refuser.
        $promo = $this->promo(['max_uses' => 1]);
        [$user, $cart, $address] = $this->cartFor([[Product::factory()->create(), 1]]);
        $sabotaged = false;

        DB::listen(function ($query) use (&$sabotaged, $promo): void {
            if (! $sabotaged && str_contains($query->sql, 'from `promo_codes`') && str_contains($query->sql, 'for update')) {
                $sabotaged = true;
                DB::table('promo_codes')->where('id', $promo->id)->update(['uses_count' => 1]);
            }
        });

        try {
            $this->order($user, $cart, $address, 'BIENVENUE');
            $this->fail('Le plafond aurait dû être respecté.');
        } catch (PromoCodeException $exception) {
            $this->assertEquals(PromoCodeException::exhausted()->getMessage(), $exception->getMessage());
        }

        $this->assertTrue($sabotaged);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_applying_codes_is_rate_limited(): void
    {
        [$user] = $this->cartFor([[Product::factory()->create(), 1]]);

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($user)->post(route('shop.checkout.promo.apply'), ['promo_code' => 'ESSAI'.$attempt]);
        }

        $this->actingAs($user)->post(route('shop.checkout.promo.apply'), ['promo_code' => 'ESSAI11'])
            ->assertStatus(429);
    }

    public function test_the_discount_shows_on_confirmation_account_and_invoice(): void
    {
        $this->promo();
        [$user, $cart, $address] = $this->cartFor([[Product::factory()->create(['price' => 10000]), 1]]);
        $order = $this->order($user, $cart, $address, 'BIENVENUE');

        $this->actingAs($user)->get(route('shop.checkout.success', $order->number))->assertSee('Remise (BIENVENUE)');
        $this->actingAs($user)->get(route('account.orders.show', $order))->assertSee('-1 000 FCFA');
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.orders.invoice', $order))->assertOk();
    }

    public function test_promo_codes_are_admin_only_and_creation_is_traced(): void
    {
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');
        $this->get(PromoCodeResource::getUrl('index'))->assertForbidden();

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        Livewire::test(CreatePromoCode::class)
            ->fillForm([
                'code' => 'tabaski26',
                'type' => PromoCodeType::Percent->value,
                'value' => 15,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $promo = PromoCode::where('code', 'TABASKI26')->firstOrFail();
        $this->assertEquals($admin->id, $promo->created_by);
        $this->assertEquals(0, $promo->uses_count);
        $this->assertEquals($admin->id, AdminActivity::where('action', 'promo.created')->value('admin_id'));
    }

    public function test_a_percentage_above_100_is_rejected_in_the_admin(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreatePromoCode::class)
            ->fillForm(['code' => 'TROP', 'type' => PromoCodeType::Percent->value, 'value' => 150])
            ->call('create')
            ->assertHasFormErrors(['value']);
    }
}

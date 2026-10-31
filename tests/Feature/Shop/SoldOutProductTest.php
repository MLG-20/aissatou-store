<?php

namespace Tests\Feature\Shop;

use App\Filament\Resources\Catalogue\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\Catalogue\ProductResource\Pages\EditProduct;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Category;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Après une vente : le produit épuisé reste visible mais passe après les
 * autres, le panier dit quoi corriger, et l'admin ne peut pas publier un
 * produit sans stock suivi.
 */
class SoldOutProductTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function product(string $name, int $stock, bool $active = true, ?string $createdAt = null): Product
    {
        $product = Product::factory()->create(['name' => $name, 'created_at' => $createdAt ?? now()]);
        ProductVariant::factory()->for($product)->create(['stock' => $stock, 'is_active' => $active]);

        return $product;
    }

    /** @return array{0: User, 1: CartItem} */
    private function cartWith(Product $product, int $quantity = 1): array
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);

        return [$user, CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->first()?->id,
            'quantity' => $quantity,
        ])];
    }

    private function sell(Product $product, int $newStock): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        app(StockService::class)->adjust($product->variants()->first(), $newStock, 'Test', Admin::query()->first());
    }

    public function test_a_sold_out_product_stays_visible_after_the_available_ones(): void
    {
        // Le plus récent est épuisé : sans le tri, il passerait en premier.
        $this->product('Pièce disponible', 3, createdAt: now()->subDay()->toDateTimeString());
        $this->product('Pièce épuisée', 0);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSeeInOrder(['Pièce disponible', 'Pièce épuisée'])
            ->assertSee('Épuisé');
    }

    public function test_the_sold_out_product_page_still_opens_and_cannot_be_bought(): void
    {
        $product = $this->product('Pièce épuisée', 0);

        $this->get(route('shop.product', $product))->assertOk()->assertSee('Rupture de stock');
    }

    public function test_a_product_whose_variants_are_all_disabled_is_not_available(): void
    {
        $product = $this->product('Pièce arrêtée', 5, active: false);

        $this->get(route('shop.product', $product))->assertOk()->assertSee('Rupture de stock');
        $this->assertFalse(Product::query()->with(['variants' => fn ($query) => $query->active()])->find($product->id)->isInStock());
    }

    public function test_the_cart_names_the_sold_out_article_and_hides_the_order_button(): void
    {
        $product = $this->product('Pièce disputée', 1);
        [$user] = $this->cartWith($product);
        $this->sell($product, 0);

        $this->actingAs($user)->get(route('shop.cart'))
            ->assertOk()
            ->assertSee('Épuisé : retirez cet article pour continuer.')
            ->assertDontSee('Passer commande');
    }

    public function test_the_cart_gives_the_remaining_quantity(): void
    {
        $product = $this->product('Pièce rare', 5);
        [$user] = $this->cartWith($product, 3);
        $this->sell($product, 2);

        $this->actingAs($user)->get(route('shop.cart'))->assertSee('Plus que 2 en stock');
    }

    public function test_the_checkout_page_sends_back_to_the_cart_when_an_article_is_sold_out(): void
    {
        $product = $this->product('Pièce disputée', 1);
        [$user] = $this->cartWith($product);
        $this->sell($product, 0);

        $this->actingAs($user)->get(route('shop.checkout'))->assertRedirect(route('shop.cart'));
    }

    public function test_an_article_sold_out_during_validation_leads_to_the_cart_with_the_reason(): void
    {
        $product = $this->product('Pièce disputée', 1);
        [$user] = $this->cartWith($product);
        $address = Address::factory()->for($user)->create(['is_default' => true]);
        $this->sell($product, 0);

        $this->actingAs($user)->post(route('shop.checkout.store'), ['address_id' => $address->id, 'provider' => 'cod'])
            ->assertRedirect(route('shop.cart'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_deleted_product_leaves_the_carts_and_pages_still_open(): void
    {
        $product = $this->product('Pièce retirée', 2);
        [$user] = $this->cartWith($product);

        $product->delete();

        $this->assertSame(0, CartItem::query()->count());
        $this->actingAs($user)->get(route('shop.cart'))->assertOk();
        $this->actingAs($user)->get(route('home'))->assertOk();
    }

    public function test_a_visible_product_needs_a_variant(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Sans stock',
                'slug' => 'sans-stock',
                'price' => 5000,
                'category_id' => Category::factory()->create()->id,
                'is_active' => true,
                'variants' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['variants' => 'required']);
    }

    public function test_a_hidden_draft_can_be_saved_without_a_variant(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Brouillon',
                'slug' => 'brouillon',
                'price' => 5000,
                'category_id' => Category::factory()->create()->id,
                'is_active' => false,
                'variants' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_a_visible_product_needs_an_active_variant(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $product = $this->product('Pièce', 2);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['variants' => [['attributes' => [], 'stock' => 2, 'is_active' => false]]])
            ->call('save')
            ->assertHasFormErrors(['variants']);
    }
}

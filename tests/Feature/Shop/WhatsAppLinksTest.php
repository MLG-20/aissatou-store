<?php

namespace Tests\Feature\Shop;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\Content\SiteSetting;
use App\Models\Customer\Address;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Liens WhatsApp pré-remplis (gratuits, sans l'API) : panier et page commande.
 */
class WhatsAppLinksTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    private User $user;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::current()->update(['contact_whatsapp' => '+221 77 123 45 67']);

        $this->user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Foulard Soie Marbré Taupe', 'price' => 6000]);
        $variant = ProductVariant::factory()->for($product)->create(['attributes' => ['couleur' => 'Taupe'], 'price_override' => null, 'stock' => 5]);
        $this->cart = Cart::create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $this->cart->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
    }

    public function test_the_cart_offers_to_order_on_whatsapp_with_its_content(): void
    {
        $response = $this->actingAs($this->user)->get(route('shop.cart'))->assertOk();

        $response->assertSee('Vous préférez commander sur WhatsApp ?');
        $link = route('shop.whatsapp', ['texte' => "Bonjour, je souhaite commander ces articles (total 12 000 FCFA) :\n- Foulard Soie Marbré Taupe (Taupe) × 2"]);
        $response->assertSee(e($link), false);
    }

    public function test_the_whatsapp_redirect_keeps_the_line_breaks(): void
    {
        $this->get(route('shop.whatsapp', ['texte' => "Bonjour\n- Foulard × 2"]))
            ->assertRedirect('https://wa.me/221771234567?text='.rawurlencode("Bonjour\n- Foulard × 2"));
    }

    public function test_the_order_page_offers_a_question_with_the_order_number(): void
    {
        $address = Address::factory()->for($this->user)->create();
        $order = app(OrderService::class)->createFromCart($this->user, $this->cart, $address, 'cod');

        $this->actingAs($this->user)->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee(e(route('shop.whatsapp', ['texte' => "Bonjour, j'ai une question sur ma commande {$order->number}."])), false);
    }

    public function test_no_link_without_a_shop_whatsapp_number(): void
    {
        SiteSetting::current()->update(['contact_whatsapp' => null]);

        $this->actingAs($this->user)->get(route('shop.cart'))
            ->assertOk()
            ->assertDontSee('Vous préférez commander sur WhatsApp ?');
    }
}

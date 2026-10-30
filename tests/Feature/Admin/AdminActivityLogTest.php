<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Admin\AdminActivityResource;
use App\Filament\Resources\Admin\AdminActivityResource\Pages\ListAdminActivities;
use App\Filament\Resources\Admin\AdminActivityResource\Pages\ViewAdminActivity;
use App\Filament\Resources\Order\OrderResource;
use App\Filament\Resources\Order\PromoCodeResource;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\AdminAudit;
use App\Services\OrderService;
use App\Support\AdminActivityDetails;
use App\Support\AdminRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;

/**
 * Journal d'activité lisible par une personne non technique : liste courte,
 * une fiche par ligne avec une phrase en clair et des détails traduits.
 */
class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase, WithCashOnDelivery;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create(['name' => 'Aissatou']);
        $this->actingAs($this->admin, 'admin');
    }

    private function order(): Order
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        return app(OrderService::class)->createFromCart($user, $cart, Address::factory()->for($user)->create(), 'cod');
    }

    public function test_each_action_reads_as_a_plain_sentence(): void
    {
        $order = $this->order();
        $status = AdminAudit::record('order.status_changed', $order, ['de' => 'nouvelle', 'vers' => 'preparation']);
        $payment = AdminAudit::record('payment.verified', $order, ['avant' => 'en_cours', 'apres' => 'confirme']);
        $settings = AdminAudit::record('settings.updated', details: ['champs' => ['low_stock_threshold', 'contact_email']], label: 'Réglages du site');

        $this->assertEquals('Aissatou a changé le statut de la commande '.$order->number.' : Nouvelle → En préparation.', AdminActivityDetails::sentence($status));
        $this->assertEquals('Aissatou a vérifié auprès de PayTech le paiement de la commande '.$order->number.' : En cours → Confirmé.', AdminActivityDetails::sentence($payment));
        $this->assertEquals('Aissatou a modifié les réglages du site : seuil d\'alerte de stock faible, e-mail de contact.', AdminActivityDetails::sentence($settings));
    }

    public function test_details_are_translated_and_never_show_raw_names(): void
    {
        $manager = Admin::factory()->manager()->create();
        $activity = AdminAudit::record('admin.updated', $manager, [
            'champs' => ['name', 'is_active'],
            'mot_de_passe_change' => true,
            'role' => ['de' => AdminRoles::MANAGER, 'vers' => AdminRoles::ADMIN],
        ]);

        $this->assertEquals([
            'Champs modifiés' => 'nom, actif',
            'Rôle' => 'Gestionnaire (catalogue, stock, commandes) → Administrateur complet',
            'Mot de passe changé' => 'oui',
        ], AdminActivityDetails::of($activity));
    }

    public function test_the_list_is_short_links_the_subject_and_hides_customer_views(): void
    {
        $order = $this->order();
        $changed = AdminAudit::record('order.status_changed', $order, ['de' => 'nouvelle', 'vers' => 'preparation']);
        $viewed = AdminAudit::record('customer.viewed', User::factory()->create());

        Livewire::test(ListAdminActivities::class)
            ->assertCanSeeTableRecords([$changed])
            ->assertCanNotSeeTableRecords([$viewed])
            ->assertSee(OrderResource::getUrl('view', ['record' => $order]), false)
            ->assertTableActionExists('view')
            ->removeTableFilter('hide_views')
            ->assertCanSeeTableRecords([$changed, $viewed]);
    }

    public function test_the_sheet_shows_the_sentence_details_and_technical_info(): void
    {
        $activity = AdminActivity::create([
            'admin_id' => $this->admin->id,
            'admin_name' => 'Aissatou',
            'action' => 'promo.updated',
            'subject_label' => 'BIENVENUE',
            'details' => ['champs' => ['min_subtotal', 'ends_at']],
            'ip' => '203.0.113.7',
        ]);

        Livewire::test(ViewAdminActivity::class, ['record' => $activity->getRouteKey()])
            ->assertSee('Aissatou a modifié le code promo BIENVENUE : minimum d&#039;achat, fin de validité.', false)
            ->assertSee('Champs modifiés')
            ->assertSee('203.0.113.7')
            ->assertDontSee('min_subtotal');
    }

    public function test_page_titles_only_capitalise_the_first_word(): void
    {
        $this->assertEquals('Journal d\'activité', AdminActivityResource::getTitleCasePluralModelLabel());
        $this->assertEquals('Codes promo', PromoCodeResource::getTitleCasePluralModelLabel());
    }
}

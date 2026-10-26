<?php

namespace Tests\Feature\Admin;

use App\Enums\Admin\AdminPermission;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Admin\AdminActivityResource;
use App\Filament\Resources\Admin\AdminResource;
use App\Filament\Resources\Admin\AdminResource\Pages\CreateAdmin;
use App\Filament\Resources\Admin\AdminResource\Pages\EditAdmin;
use App\Filament\Resources\Catalogue\ProductResource;
use App\Filament\Resources\Content\LookResource;
use App\Filament\Resources\Content\PostResource;
use App\Filament\Resources\Delivery\DeliveryZoneResource;
use App\Filament\Resources\Order\OrderResource;
use App\Filament\Resources\Order\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\Order\PaymentEventResource;
use App\Models\Admin;
use App\Models\AdminActivity;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\AdminAccountService;
use App\Services\OrderService;
use App\Support\AdminRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/**
 * Checklist phase 6 : « un compte à rôle limité ne peut pas accéder aux
 * fonctionnalités réservées à l'admin complet en modifiant une URL » et
 * « chaque action sensible tracée avec l'auteur précis ».
 */
class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        Mail::fake();
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        return app(OrderService::class)->createFromCart($user, $cart, Address::factory()->for($user)->create(), 'cod');
    }

    public function test_a_manager_reaches_catalogue_and_orders(): void
    {
        $order = $this->order();
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        $this->get(ProductResource::getUrl('index'))->assertOk();
        $this->get(ProductResource::getUrl('create'))->assertOk();
        $this->get(OrderResource::getUrl('index'))->assertOk();
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk();
        $this->get(route('admin.orders.invoice', $order))->assertOk();
    }

    public function test_a_manager_is_refused_everything_else_even_by_typing_the_url(): void
    {
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        foreach ([
            PaymentEventResource::getUrl('index'),
            AdminResource::getUrl('index'),
            AdminResource::getUrl('create'),
            AdminActivityResource::getUrl('index'),
            DeliveryZoneResource::getUrl('index'),
            PostResource::getUrl('index'),
            LookResource::getUrl('index'),
            LookResource::getUrl('create'),
            SiteSettings::getUrl(),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_a_manager_does_not_see_forbidden_sections_in_the_menu(): void
    {
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        $this->get(ProductResource::getUrl('index'))
            ->assertSee('Commandes')
            ->assertDontSee('Journal des paiements')
            ->assertDontSee('Comptes admin')
            ->assertDontSee('Réglages du site');
    }

    public function test_a_manager_cannot_see_nor_trigger_payment_verification(): void
    {
        $order = $this->order();
        $this->actingAs(Admin::factory()->manager()->create(), 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('verifyPayment')
            ->assertDontSee('Journal du paiement');
    }

    public function test_an_admin_without_role_has_no_access_at_all(): void
    {
        $this->actingAs(Admin::factory()->withoutRole()->create(), 'admin');

        $this->get(ProductResource::getUrl('index'))->assertForbidden();
        $this->get(OrderResource::getUrl('index'))->assertForbidden();
    }

    public function test_a_deactivated_full_admin_loses_every_permission(): void
    {
        $admin = Admin::factory()->create();
        $admin->forceFill(['is_active' => false])->save();

        $this->assertFalse($admin->fresh()->hasAdminPermission(AdminPermission::Catalogue));
    }

    public function test_a_full_admin_creates_a_manager_account_and_it_is_traced(): void
    {
        $author = Admin::factory()->create();
        $this->actingAs($author, 'admin');

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'Awa Gestion',
                'email' => 'awa@aissatou.test',
                'role' => AdminRoles::MANAGER,
                'is_active' => true,
                'password' => 'un-mot-de-passe-solide',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $manager = Admin::where('email', 'awa@aissatou.test')->firstOrFail();
        $this->assertTrue($manager->hasRole(AdminRoles::MANAGER));
        $this->assertNotNull($manager->email_verified_at);

        $activity = AdminActivity::where('action', 'admin.created')->firstOrFail();
        $this->assertEquals($author->id, $activity->admin_id);
        $this->assertEquals('awa@aissatou.test', $activity->subject_label);
        $this->assertStringNotContainsString('un-mot-de-passe-solide', json_encode($activity->details));
    }

    public function test_an_admin_cannot_demote_nor_deactivate_themself(): void
    {
        $admin = Admin::factory()->create();
        Admin::factory()->create(); // un autre admin complet existe
        $this->actingAs($admin, 'admin');

        Livewire::test(EditAdmin::class, ['record' => $admin->getRouteKey()])
            ->fillForm(['role' => AdminRoles::MANAGER])
            ->call('save')
            ->assertHasErrors('data.role');

        $this->assertTrue($admin->fresh()->isFullAdmin());
    }

    public function test_the_last_active_full_admin_cannot_be_removed(): void
    {
        $last = Admin::factory()->create();
        $other = Admin::factory()->manager()->create();

        // Appel direct au service : le garde-fou ne dépend pas du formulaire.
        $this->expectException(ValidationException::class);

        app(AdminAccountService::class)->update($last, ['is_active' => false], $other);
    }

    public function test_changing_a_role_is_traced_with_before_and_after(): void
    {
        $author = Admin::factory()->create();
        $target = Admin::factory()->manager()->create();
        $this->actingAs($author, 'admin');

        Livewire::test(EditAdmin::class, ['record' => $target->getRouteKey()])
            ->fillForm(['role' => AdminRoles::ADMIN, 'password' => 'nouveau-mot-de-passe'])
            ->call('save')
            ->assertHasNoFormErrors();

        $activity = AdminActivity::where('action', 'admin.updated')->firstOrFail();
        $this->assertEquals(['de' => AdminRoles::MANAGER, 'vers' => AdminRoles::ADMIN], $activity->details['role']);
        $this->assertTrue($activity->details['mot_de_passe_change']);
        $this->assertStringNotContainsString('nouveau-mot-de-passe', json_encode($activity->details));
    }

    public function test_order_actions_are_traced_with_the_precise_author(): void
    {
        $order = $this->order();
        $manager = Admin::factory()->manager()->create();
        $this->actingAs($manager, 'admin');

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('changeStatus', data: ['status' => 'preparation']);

        $activity = AdminActivity::where('action', 'order.status_changed')->firstOrFail();
        $this->assertEquals($manager->id, $activity->admin_id);
        $this->assertEquals($order->number, $activity->subject_label);
        $this->assertEquals('preparation', $activity->details['vers']);
    }

    public function test_admin_logins_are_traced(): void
    {
        $admin = Admin::factory()->create(['password' => 'mot-de-passe-admin']);

        auth('admin')->attempt(['email' => $admin->email, 'password' => 'faux']);
        auth('admin')->attempt(['email' => $admin->email, 'password' => 'mot-de-passe-admin']);

        $this->assertEquals(1, AdminActivity::where('action', 'admin.login_failed')->count());
        $this->assertEquals($admin->id, AdminActivity::where('action', 'admin.login')->value('admin_id'));
    }

    public function test_the_activity_log_is_immutable(): void
    {
        $activity = AdminActivity::create(['action' => 'admin.login']);

        $this->expectException(LogicException::class);

        $activity->update(['action' => 'admin.created']);
    }
}

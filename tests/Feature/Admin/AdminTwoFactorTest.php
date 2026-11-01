<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Auth\TwoFactorChallenge;
use App\Filament\Pages\Auth\TwoFactorSetup;
use App\Filament\Resources\Admin\AdminResource\Pages\ListAdmins;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Models\Admin;
use App\Services\Admin\TwoFactor;
use App\Support\AdminRoles;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Un mot de passe volé ne doit pas suffire pour entrer dans le back-office.
 */
class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Coupé pour les autres tests (cf. TestCase) : ici, c'est le sujet.
        $this->withMiddleware(EnsureAdminTwoFactor::class);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = Admin::factory()->create();
        $this->actingAs($this->admin, 'admin');
    }

    private function enroll(Admin $admin): array
    {
        $service = app(TwoFactor::class);
        $service->start($admin);

        return $service->confirm($admin, $this->currentCode($admin));
    }

    /** google2fa suit la vraie horloge : on oublie le dernier code accepté plutôt que d'attendre 30 secondes. */
    private function nextTimeWindow(): void
    {
        $this->admin->forceFill(['two_factor_last_timestep' => null])->save();
    }

    private function currentCode(Admin $admin): string
    {
        return (new Google2FA)->getCurrentOtp($admin->fresh()->two_factor_secret);
    }

    public function test_an_admin_without_two_factor_is_sent_to_the_setup_screen(): void
    {
        $this->get('/admin')->assertRedirect(route('filament.admin.two-factor.setup'));
        $this->get('/admin/clients')->assertRedirect(route('filament.admin.two-factor.setup'));
    }

    public function test_an_enrolled_admin_with_only_a_password_is_sent_to_the_code_screen(): void
    {
        $this->enroll($this->admin);
        session()->flush();

        $this->get('/admin')->assertRedirect(route('filament.admin.two-factor.challenge'));
    }

    public function test_admin_routes_outside_filament_are_protected_too(): void
    {
        $this->get('/admin/order/payment-events')->assertRedirect(route('filament.admin.two-factor.setup'));
    }

    public function test_the_livewire_requests_of_the_panel_are_protected_too(): void
    {
        $this->assertContains(EnsureAdminTwoFactor::class, Livewire::getPersistentMiddleware());
    }

    public function test_setup_shows_a_qr_code_and_keeps_the_same_secret_on_reload(): void
    {
        Livewire::test(TwoFactorSetup::class)->assertSee('<svg', false);
        $secret = $this->admin->fresh()->two_factor_secret;

        Livewire::test(TwoFactorSetup::class);

        $this->assertNotEmpty($secret);
        $this->assertSame($secret, $this->admin->fresh()->two_factor_secret);
    }

    public function test_setup_refuses_a_wrong_code(): void
    {
        Livewire::test(TwoFactorSetup::class)
            ->set('data.code', '000000')
            ->call('confirm')
            ->assertHasErrors('data.code');

        $this->assertNull($this->admin->fresh()->two_factor_confirmed_at);
    }

    public function test_setup_gives_recovery_codes_then_opens_the_back_office(): void
    {
        $page = Livewire::test(TwoFactorSetup::class);

        $page->set('data.code', $this->currentCode($this->admin))
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertCount(8, $page->get('recoveryCodes'));
        $this->assertNotNull($this->admin->fresh()->two_factor_confirmed_at);
        $this->get('/admin')->assertOk();
    }

    public function test_the_secret_and_recovery_codes_are_not_stored_in_clear(): void
    {
        $codes = $this->enroll($this->admin);

        $row = \DB::table('admins')->find($this->admin->id);

        $this->assertStringNotContainsString($this->admin->fresh()->two_factor_secret, $row->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], (string) $row->two_factor_recovery_codes);
        $this->assertStringNotContainsString($codes[0], (string) json_encode($this->admin->fresh()->two_factor_recovery_codes));
    }

    public function test_the_right_code_opens_the_back_office(): void
    {
        $this->enroll($this->admin);
        session()->flush();
        $this->nextTimeWindow();

        Livewire::test(TwoFactorChallenge::class)
            ->set('data.code', $this->currentCode($this->admin))
            ->call('verify')
            ->assertHasNoErrors();

        $this->get('/admin')->assertOk();
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $this->enroll($this->admin);
        session()->flush();

        Livewire::test(TwoFactorChallenge::class)
            ->set('data.code', '000000')
            ->call('verify')
            ->assertHasErrors('data.code');

        $this->get('/admin')->assertRedirect(route('filament.admin.two-factor.challenge'));
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $this->enroll($this->admin);
        session()->flush();
        $this->nextTimeWindow();
        $code = $this->currentCode($this->admin);

        Livewire::test(TwoFactorChallenge::class)->set('data.code', $code)->call('verify')->assertHasNoErrors();
        session()->flush();

        Livewire::test(TwoFactorChallenge::class)->set('data.code', $code)->call('verify')->assertHasErrors('data.code');
    }

    public function test_too_many_wrong_codes_block_even_the_right_one_for_a_while(): void
    {
        $this->enroll($this->admin);
        session()->flush();
        $this->nextTimeWindow();

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(TwoFactorChallenge::class)->set('data.code', '000000')->call('verify');
        }

        Livewire::test(TwoFactorChallenge::class)
            ->set('data.code', $this->currentCode($this->admin))
            ->call('verify')
            ->assertHasErrors('data.code');

        $this->get('/admin')->assertRedirect(route('filament.admin.two-factor.challenge'));
    }

    public function test_setup_also_limits_wrong_codes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(TwoFactorSetup::class)->set('data.code', '000000')->call('confirm');
        }

        Livewire::test(TwoFactorSetup::class)
            ->set('data.code', $this->currentCode($this->admin))
            ->call('confirm')
            ->assertHasErrors('data.code');

        $this->assertNull($this->admin->fresh()->two_factor_confirmed_at);
    }

    public function test_a_recovery_code_works_only_once(): void
    {
        $codes = $this->enroll($this->admin);
        session()->flush();

        Livewire::test(TwoFactorChallenge::class)->set('data.code', $codes[0])->call('verify')->assertHasNoErrors();
        $this->get('/admin')->assertOk();
        session()->flush();

        Livewire::test(TwoFactorChallenge::class)->set('data.code', $codes[0])->call('verify')->assertHasErrors('data.code');
        Livewire::test(TwoFactorChallenge::class)->set('data.code', $codes[1])->call('verify')->assertHasNoErrors();
    }

    public function test_the_session_of_one_admin_does_not_open_another_account(): void
    {
        $this->enroll($this->admin);
        $other = Admin::factory()->create();
        $this->enroll($other);
        session()->flush();
        app(TwoFactor::class)->markPassed($this->admin);

        $this->actingAs($other, 'admin')->get('/admin')->assertRedirect(route('filament.admin.two-factor.challenge'));
    }

    public function test_a_reset_forces_a_new_setup_even_on_an_open_session(): void
    {
        $this->enroll($this->admin);
        app(TwoFactor::class)->markPassed($this->admin);
        $this->get('/admin')->assertOk();

        Artisan::call('admin:reset-two-factor', ['email' => $this->admin->email]);

        // Une vraie requête relit le compte en base : on fait de même.
        $this->actingAs($this->admin->fresh(), 'admin');

        $this->assertNull($this->admin->fresh()->two_factor_secret);
        $this->get('/admin')->assertRedirect(route('filament.admin.two-factor.setup'));
    }

    public function test_another_admin_can_reset_but_not_oneself(): void
    {
        $this->enroll($this->admin);
        $this->admin->assignRole(AdminRoles::ADMIN);
        app(TwoFactor::class)->markPassed($this->admin);
        $other = Admin::factory()->create();
        $this->enroll($other);

        Livewire::test(ListAdmins::class)
            ->assertTableActionHidden('resetTwoFactor', $this->admin)
            ->callTableAction('resetTwoFactor', $other);

        $this->assertNull($other->fresh()->two_factor_secret);
        $this->assertDatabaseHas('admin_activities', ['action' => 'admin.two_factor_reset', 'subject_id' => $other->id]);
    }

    public function test_the_secret_never_leaves_through_serialization(): void
    {
        $this->enroll($this->admin);

        $array = $this->admin->fresh()->toArray();

        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
    }
}

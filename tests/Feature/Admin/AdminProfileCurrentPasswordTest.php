<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Une session restée ouverte suffisait pour changer le mot de passe ou l'e-mail
 * d'un compte admin, donc pour en prendre le contrôle.
 */
class AdminProfileCurrentPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = Admin::factory()->create(['name' => 'Avant', 'email' => 'avant@example.com']);
        $this->actingAs($this->admin, 'admin');
    }

    private function profile(array $data): Testable
    {
        return Livewire::test(Filament::getCurrentPanel()->getProfilePage())
            ->fillForm($data)
            ->call('save');
    }

    private function newPassword(array $extra = []): array
    {
        return [
            'password' => 'nouveau-mot-de-passe',
            'passwordConfirmation' => 'nouveau-mot-de-passe',
            ...$extra,
        ];
    }

    public function test_the_password_cannot_change_without_the_current_one(): void
    {
        $this->profile($this->newPassword())->assertHasFormErrors(['currentPassword' => 'required']);

        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
    }

    public function test_the_password_cannot_change_with_a_wrong_current_one(): void
    {
        $this->profile($this->newPassword(['currentPassword' => 'mauvais']))->assertHasFormErrors(['currentPassword']);

        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
    }

    public function test_too_many_wrong_current_passwords_block_even_the_right_one(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->profile($this->newPassword(['currentPassword' => 'mauvais']))->assertHasFormErrors(['currentPassword']);
        }

        $this->profile($this->newPassword(['currentPassword' => 'password']))->assertHasFormErrors(['currentPassword']);

        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
    }

    public function test_the_password_changes_with_the_right_current_one(): void
    {
        $this->profile($this->newPassword(['currentPassword' => 'password']))->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $this->admin->fresh()->password));
    }

    public function test_the_email_cannot_change_without_the_current_password(): void
    {
        $this->profile(['email' => 'pirate@example.com'])->assertHasFormErrors(['currentPassword' => 'required']);

        $this->assertSame('avant@example.com', $this->admin->fresh()->email);
    }

    public function test_the_email_changes_with_the_current_password(): void
    {
        $this->profile(['email' => 'apres@example.com', 'currentPassword' => 'password'])->assertHasNoFormErrors();

        $this->assertSame('apres@example.com', $this->admin->fresh()->email);
    }

    public function test_the_name_can_change_without_the_current_password(): void
    {
        $this->profile(['name' => 'Après'])->assertHasNoFormErrors();

        $this->assertSame('Après', $this->admin->fresh()->name);
    }
}

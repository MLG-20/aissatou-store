<?php

namespace Tests\Feature\Account;

use App\Models\Customer\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_addresses(): void
    {
        $response = $this->get(route('account.addresses.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_first_address_is_automatically_set_as_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload());

        $address = $user->addresses()->first();

        $this->assertNotNull($address);
        $this->assertTrue($address->is_default);
    }

    public function test_setting_a_new_default_address_unsets_the_previous_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload());
        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload(['is_default' => true]));

        $addresses = $user->addresses()->orderBy('id')->get();

        $this->assertFalse($addresses[0]->fresh()->is_default);
        $this->assertTrue($addresses[1]->fresh()->is_default);
    }

    public function test_deleting_the_default_address_promotes_another_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload());
        $this->actingAs($user)->post(route('account.addresses.store'), $this->addressPayload());

        $addresses = $user->addresses()->orderBy('id')->get();

        $this->actingAs($user)->delete(route('account.addresses.destroy', $addresses[0]));

        $this->assertTrue($addresses[1]->fresh()->is_default);
    }

    public function test_user_cannot_update_another_users_address(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $address = Address::factory()->for($owner)->create();

        $response = $this->actingAs($attacker)->put(
            route('account.addresses.update', $address),
            $this->addressPayload(['recipient_name' => 'Hacked'])
        );

        $response->assertForbidden();
        $this->assertNotEquals('Hacked', $address->fresh()->recipient_name);
    }

    public function test_user_cannot_delete_another_users_address(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $address = Address::factory()->for($owner)->create();

        $response = $this->actingAs($attacker)->delete(route('account.addresses.destroy', $address));

        $response->assertForbidden();
        $this->assertNotNull($address->fresh());
    }

    public function test_address_requires_mandatory_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('account.addresses.store'), []);

        $response->assertSessionHasErrors(['recipient_name', 'phone', 'city', 'address_line']);
    }

    public function test_address_phone_must_be_a_senegalese_number(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.addresses.store'), $this->addressPayload(['phone' => '77313166']))
            ->assertSessionHasErrors(['phone' => 'Numéro sénégalais à 9 chiffres attendu, par exemple 77 123 45 67.']);

        $this->actingAs($user)
            ->post(route('account.addresses.store'), $this->addressPayload(['phone' => '+221 77 123 45 67']))
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Aminata Diop',
            'phone' => '771234567',
            'city' => 'Dakar',
            'address_line' => 'Rue 10, Sacré-Cœur',
            'landmark' => 'Face à la pharmacie',
        ], $overrides);
    }
}

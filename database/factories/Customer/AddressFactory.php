<?php

namespace Database\Factories\Customer;

use App\Models\Customer\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('77#######'),
            'city' => fake()->city(),
            'address_line' => fake()->streetAddress(),
            'landmark' => null,
            'is_default' => false,
        ];
    }
}

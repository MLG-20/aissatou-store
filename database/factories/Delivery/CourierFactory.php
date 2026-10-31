<?php

namespace Database\Factories\Delivery;

use App\Enums\Delivery\CourierType;
use App\Models\Delivery\Courier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Courier>
 */
class CourierFactory extends Factory
{
    protected $model = Courier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'phone' => fake()->numerify('77#######'),
            'type' => CourierType::Internal,
            'is_active' => true,
        ];
    }
}

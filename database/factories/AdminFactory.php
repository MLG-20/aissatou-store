<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Support\AdminRoles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Admin complet par défaut (Phase 6A) : sans rôle, un compte n'a plus
     * accès à rien. `manager()` / `withoutRole()` pour les cas restreints.
     */
    public function configure(): static
    {
        // S'exécute avant les callbacks des états : manager()/withoutRole()
        // remplacent ensuite ce rôle par défaut.
        return $this->afterCreating(function (Admin $admin): void {
            AdminRoles::sync();
            $admin->assignRole(AdminRoles::ADMIN);
        });
    }

    public function manager(): static
    {
        return $this->afterCreating(fn (Admin $admin) => $admin->syncRoles([AdminRoles::MANAGER]));
    }

    public function withoutRole(): static
    {
        return $this->afterCreating(fn (Admin $admin) => $admin->syncRoles([]));
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

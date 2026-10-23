<?php

namespace Database\Seeders;

use App\Support\AdminRoles;
use Illuminate\Database\Seeder;

/**
 * Rôles + permissions du back-office (Phase 6A). Idempotent : à rejouer à
 * chaque déploiement (`db:seed --class=RolePermissionSeeder --force`).
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        AdminRoles::sync();
    }
}

<?php

namespace App\Support;

use App\Enums\Admin\AdminPermission;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles du back-office et leurs permissions (décision Ahmed, 2026-10-23) :
 * - `admin` (admin complet) : tout ;
 * - `employee` (gestionnaire) : catalogue, stock, commandes — rien d'autre.
 *
 * `sync()` est idempotent : à rejouer à chaque déploiement (seeder) pour
 * aligner la base sur ce fichier, qui fait foi.
 */
class AdminRoles
{
    public const ADMIN = 'admin';

    public const MANAGER = 'employee';

    private const GUARD = 'admin';

    /** @return array<string, string> Pour les listes déroulantes. */
    public static function options(): array
    {
        return [
            self::ADMIN => 'Administrateur complet',
            self::MANAGER => 'Gestionnaire (catalogue, stock, commandes)',
        ];
    }

    public static function label(?string $role): string
    {
        return self::options()[$role] ?? 'Aucun rôle (aucun accès)';
    }

    /** @return list<AdminPermission> */
    public static function permissionsFor(string $role): array
    {
        return match ($role) {
            self::ADMIN => AdminPermission::cases(),
            self::MANAGER => [AdminPermission::Catalogue, AdminPermission::Stock, AdminPermission::Orders],
            default => [],
        };
    }

    public static function sync(): void
    {
        foreach (AdminPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, self::GUARD);
        }

        foreach (array_keys(self::options()) as $role) {
            Role::findOrCreate($role, self::GUARD)->syncPermissions(
                array_map(fn (AdminPermission $permission) => $permission->value, self::permissionsFor($role))
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

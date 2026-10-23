<?php

namespace App\Filament\Concerns;

use App\Enums\Admin\AdminPermission;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Ressource Filament réservée à une permission (Phase 6A).
 *
 * Toutes les autorisations d'une ressource (lister, voir, créer, modifier,
 * supprimer) passent par `can()`, et l'accès aux pages — y compris par URL
 * directe — par `canAccess()` → `canViewAny()` → `can()`. Le contrôle est
 * donc rejoué côté serveur à chaque requête : un gestionnaire qui tape
 * l'URL d'une ressource interdite reçoit un 403, pas seulement un menu
 * sans le lien.
 */
trait RequiresAdminPermission
{
    abstract protected static function requiredPermission(): AdminPermission;

    public static function can(string $action, ?Model $record = null): bool
    {
        $admin = Filament::auth()->user();

        if (! $admin instanceof Admin || ! $admin->hasAdminPermission(static::requiredPermission())) {
            return false;
        }

        return parent::can($action, $record);
    }
}

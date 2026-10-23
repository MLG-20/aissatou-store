<?php

namespace App\Filament\Concerns;

use App\Enums\Admin\AdminPermission;
use App\Models\Admin;
use Filament\Facades\Filament;

/**
 * Équivalent de RequiresAdminPermission pour une page Filament autonome
 * (hors ressource) : `canAccess()` est vérifié au montage de la page et
 * pour l'affichage dans la navigation.
 */
trait PageRequiresAdminPermission
{
    abstract protected static function requiredPermission(): AdminPermission;

    public static function canAccess(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof Admin && $admin->hasAdminPermission(static::requiredPermission());
    }
}

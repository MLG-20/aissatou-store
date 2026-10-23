<?php

namespace App\Filament\Concerns;

use App\Enums\Admin\AdminPermission;
use App\Models\Admin;
use Filament\Facades\Filament;

/**
 * Widget réservé à une permission (Phase 6D) : `canView()` est vérifié par
 * Filament avant d'afficher ET avant de monter le composant Livewire.
 */
trait WidgetRequiresAdminPermission
{
    abstract protected static function requiredPermission(): AdminPermission;

    public static function canView(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof Admin && $admin->hasAdminPermission(static::requiredPermission());
    }
}

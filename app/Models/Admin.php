<?php

namespace App\Models;

use App\Enums\Admin\AdminPermission;
use App\Support\AdminRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'admin';

    // Appelée par Filament à chaque tentative de connexion et à chaque requête
    // authentifiée ; désactiver un admin (is_active=false) le bloque
    // immédiatement sans avoir à supprimer le compte ou révoquer sa session.
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasVerifiedEmail();
    }

    /**
     * Point unique de contrôle des droits du back-office (Phase 6A).
     * `checkPermissionTo` renvoie false (sans exception) pour une permission
     * inconnue : en cas de doute, l'accès est refusé.
     */
    public function hasAdminPermission(AdminPermission $permission): bool
    {
        return $this->is_active && $this->checkPermissionTo($permission->value, 'admin');
    }

    public function isFullAdmin(): bool
    {
        return $this->hasRole(AdminRoles::ADMIN, 'admin');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}

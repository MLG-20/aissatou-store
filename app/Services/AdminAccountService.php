<?php

namespace App\Services;

use App\Models\Admin;
use App\Support\AdminRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Création / modification des comptes admin (Phase 6A). Les garde-fous sont
 * ici, côté serveur — pas seulement dans le formulaire :
 * - on ne peut ni se rétrograder ni se désactiver soi-même ;
 * - il reste toujours au moins un admin complet actif (pas de verrouillage
 *   total du back-office) ;
 * - toute création/modification est journalisée avec son auteur.
 * Pas de suppression : on désactive (le journal garde l'auteur de chaque
 * action passée).
 */
class AdminAccountService
{
    /** @param  array{name: string, email: string, password: string, role: string, is_active?: bool}  $data */
    public function create(array $data, Admin $author): Admin
    {
        $this->assertKnownRole($data['role']);

        return DB::transaction(function () use ($data, $author): Admin {
            $admin = new Admin([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            // Hors fillable : jamais assignables depuis une requête.
            $admin->is_active = (bool) ($data['is_active'] ?? true);
            // Créé par un admin complet qui se porte garant de l'adresse.
            $admin->email_verified_at = now();
            $admin->save();
            $admin->syncRoles([$data['role']]);

            AdminAudit::record('admin.created', $admin, [
                'role' => $data['role'],
                'actif' => $admin->is_active,
            ], $author);

            return $admin;
        });
    }

    /** @param  array{name?: string, email?: string, password?: ?string, role?: string, is_active?: bool}  $data */
    public function update(Admin $admin, array $data, Admin $author): Admin
    {
        $role = $data['role'] ?? $admin->getRoleNames()->first();
        $active = (bool) ($data['is_active'] ?? $admin->is_active);
        $this->assertKnownRole($role);

        if ($admin->is($author) && ($role !== AdminRoles::ADMIN || ! $active)) {
            throw ValidationException::withMessages([
                'data.role' => 'Vous ne pouvez pas retirer vos propres droits ni désactiver votre propre compte.',
            ]);
        }

        return DB::transaction(function () use ($admin, $data, $role, $active, $author): Admin {
            // Verrou : deux admins qui se rétrogradent mutuellement en même
            // temps ne peuvent pas laisser la boutique sans admin complet.
            Admin::query()->lockForUpdate()->get(['id']);

            $wasFullActive = $admin->is_active && $admin->isFullAdmin();
            $staysFullActive = $active && $role === AdminRoles::ADMIN;

            if ($wasFullActive && ! $staysFullActive && $this->activeFullAdminCount() <= 1) {
                throw ValidationException::withMessages([
                    'data.role' => 'Il doit rester au moins un administrateur complet actif.',
                ]);
            }

            $admin->fill(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ]));

            if (filled($data['password'] ?? null)) {
                $admin->password = $data['password'];
            }

            $admin->is_active = $active;
            $changes = array_keys($admin->getDirty());
            $admin->save();

            $previousRole = $admin->getRoleNames()->first();
            $admin->syncRoles([$role]);

            $details = array_values(array_diff($changes, ['password', 'updated_at']));
            $details = ['champs' => $details];

            if (in_array('password', $changes, true)) {
                $details['mot_de_passe_change'] = true;
            }

            if ($previousRole !== $role) {
                $details['role'] = ['de' => $previousRole, 'vers' => $role];
            }

            AdminAudit::record('admin.updated', $admin, $details, $author);

            return $admin;
        });
    }

    private function activeFullAdminCount(): int
    {
        return Admin::role(AdminRoles::ADMIN, 'admin')->where('is_active', true)->count();
    }

    private function assertKnownRole(?string $role): void
    {
        if (! array_key_exists((string) $role, AdminRoles::options())) {
            throw ValidationException::withMessages(['data.role' => 'Rôle inconnu.']);
        }
    }
}

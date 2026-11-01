<?php

namespace App\Services;

use App\Enums\Order\OrderStatus;
use App\Models\Cart\Cart;
use App\Models\User;
use App\Models\Wishlist\Wishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Suppression d'un compte par sa propriétaire (décision Ahmed, 2026-11-01).
 *
 * Sans commande, le compte est réellement supprimé. Avec des commandes, il est
 * anonymisé : ses données personnelles partent, mais les commandes restent avec
 * le destinataire et l'adresse figés à l'achat, pour la comptabilité. Une
 * commande en cours bloque la demande : elle doit encore pouvoir être livrée.
 */
class AccountDeletionService
{
    /** @throws ValidationException */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $this->ensureDeletable($user);

            $email = $user->email;

            $this->removePersonalData($user);

            if ($user->orders()->exists()) {
                $this->anonymize($user);
            } else {
                $user->delete();
            }

            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });
    }

    /** @throws ValidationException */
    public function ensureDeletable(User $user): void
    {
        if ($user->orders()->whereIn('status', [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Shipped])->exists()) {
            throw ValidationException::withMessages([
                'password' => 'Vous avez une commande en cours. Vous pourrez supprimer votre compte une fois qu\'elle sera livrée ou annulée.',
            ])->errorBag('userDeletion');
        }
    }

    private function removePersonalData(User $user): void
    {
        $user->addresses()->delete();
        Cart::query()->where('user_id', $user->id)->get()->each->delete();
        Wishlist::query()->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        // Déconnecte aussi ses autres appareils.
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    /** L'adresse `.invalid` ne peut recevoir aucun e-mail, ni servir à se reconnecter. */
    private function anonymize(User $user): void
    {
        $user->forceFill([
            'name' => 'Compte supprimé',
            'email' => 'compte-supprime-'.$user->id.'@anonyme.invalid',
            'phone' => null,
            'password' => Str::random(64),
            'email_verified_at' => null,
            'remember_token' => null,
            'anonymized_at' => now(),
        ])->save();
    }
}

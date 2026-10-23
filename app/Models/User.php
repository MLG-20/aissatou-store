<?php

namespace App\Models;

use App\Enums\Order\PaymentStatus;
use App\Models\Cart\Cart;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasOne<Cart, $this> */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /*
    | Segments clients (Phase 6E, règles validées par Ahmed le 2026-10-23).
    | Calculés en SQL (sous-requêtes) : filtrables et paginables même avec
    | beaucoup de clients. « Commande payée » = paiement confirmé, datée à
    | l'encaissement — même définition que le chiffre d'affaires.
    | Les segments peuvent se recouper (une cliente régulière peut aussi
    | avoir un panier abandonné).
    */

    /** Réguliers : au moins 2 commandes payées sur les 6 derniers mois. */
    public function scopeRegularCustomers(Builder $query): Builder
    {
        return $query->whereRaw('('.self::paidOrdersSql().' AND p.confirmed_at >= ?) >= 2', [now()->subMonths(6)]);
    }

    /** Inactifs : aucune commande payée depuis 90 jours (ou jamais). */
    public function scopeInactiveCustomers(Builder $query): Builder
    {
        return $query->whereRaw('('.self::paidOrdersSql().' AND p.confirmed_at >= ?) = 0', [now()->subDays(90)]);
    }

    /** Panier abandonné en cours (même règle que le tableau de bord). */
    public function scopeWithAbandonedCart(Builder $query): Builder
    {
        return $query->whereHas('cart', fn (Builder $cart) => $cart->abandoned());
    }

    /**
     * Sous-requêtes d'agrégats d'achat pour les listes du back-office :
     * nombre de commandes payées, total dépensé, dernier encaissement.
     */
    public function scopeWithPurchaseStats(Builder $query): Builder
    {
        $paid = fn (string $aggregate) => '(SELECT '.$aggregate.' FROM orders o JOIN payments p ON p.order_id = o.id'
            ." WHERE o.user_id = users.id AND p.status = '".PaymentStatus::Confirmed->value."')";

        return $query->select('users.*')
            ->selectRaw($paid('COUNT(*)').' as paid_orders_count')
            ->selectRaw($paid('COALESCE(SUM(o.total), 0)').' as total_spent')
            ->selectRaw($paid('MAX(p.confirmed_at)').' as last_paid_at');
    }

    /** @return list<string> Segments de la cliente, pour affichage. */
    public function segments(): array
    {
        return array_values(array_filter([
            static::query()->whereKey($this->id)->regularCustomers()->exists() ? 'Régulière' : null,
            static::query()->whereKey($this->id)->inactiveCustomers()->exists() ? 'Inactive' : null,
            static::query()->whereKey($this->id)->withAbandonedCart()->exists() ? 'Panier abandonné' : null,
        ]));
    }

    private static function paidOrdersSql(): string
    {
        return 'SELECT COUNT(*) FROM orders o JOIN payments p ON p.order_id = o.id'
            ." WHERE o.user_id = users.id AND p.status = '".PaymentStatus::Confirmed->value."'";
    }
}

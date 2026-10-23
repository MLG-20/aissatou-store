<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Journal d'activité admin immuable (Phase 6A) : on ajoute, on ne réécrit
 * jamais — même principe que le journal des paiements.
 */
#[Fillable(['admin_id', 'admin_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'details', 'ip'])]
class AdminActivity extends Model
{
    public const UPDATED_AT = null;

    /** Libellés lisibles des actions journalisées. */
    public const ACTIONS = [
        'admin.login' => 'Connexion',
        'admin.login_failed' => 'Échec de connexion',
        'admin.created' => 'Compte admin créé',
        'admin.updated' => 'Compte admin modifié',
        'order.status_changed' => 'Statut de commande changé',
        'order.delivery_assigned' => 'Livraison organisée',
        'payment.verified' => 'Paiement vérifié auprès du fournisseur',
        'settings.updated' => 'Réglages du site modifiés',
        'promo.created' => 'Code promo créé',
        'promo.updated' => 'Code promo modifié',
        'customer.viewed' => 'Fiche client consultée',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'activité est immuable.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'activité est immuable.'));
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}

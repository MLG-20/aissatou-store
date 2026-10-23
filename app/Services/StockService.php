<?php

namespace App\Services;

use App\Enums\Admin\AdminPermission;
use App\Enums\Catalogue\StockMovementType;
use App\Mail\LowStockAlert;
use App\Models\Admin;
use App\Models\Catalogue\ProductVariant;
use App\Models\Catalogue\StockMovement;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Seul chemin par lequel le stock d'une déclinaison change (Phase 6B) :
 * vente, remise en stock à l'annulation, ajustement manuel motivé. Chaque
 * variation écrit une ligne immuable dans `stock_movements` (qui, quand,
 * combien, pourquoi, stock résultant) — ProductVariant refuse toute autre
 * écriture de `stock`.
 *
 * Alerte stock faible : envoyée UNE fois au franchissement du seuil
 * (`low_stock_notified_at`), réarmée quand le stock repasse au-dessus.
 */
class StockService
{
    private static int $writing = 0;

    public static function isWriting(): bool
    {
        return self::$writing > 0;
    }

    /** Vente : DANS la transaction de commande, déclinaison déjà verrouillée. */
    public function removeForOrder(ProductVariant $locked, int $quantity, Order $order): StockMovement
    {
        return $this->apply($locked, -$quantity, StockMovementType::Sale, order: $order);
    }

    /** Annulation (admin ou délai de paiement) : les articles reviennent en vente. */
    public function restoreForOrder(Order $order, ?Admin $admin = null): void
    {
        foreach ($order->items()->whereNotNull('product_variant_id')->get() as $item) {
            $locked = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();

            if ($locked) {
                $this->apply($locked, $item->quantity, StockMovementType::Cancellation, order: $order, admin: $admin);
            }
        }
    }

    /**
     * Ajustement manuel (inventaire, casse, réassort) : nouvelle quantité
     * absolue + motif OBLIGATOIRE, auteur tracé.
     *
     * @throws ValidationException
     */
    public function adjust(ProductVariant $variant, int $newStock, string $reason, Admin $admin): StockMovement
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'Indiquez le motif de l\'ajustement.']);
        }

        if ($newStock < 0) {
            throw ValidationException::withMessages(['stock' => 'Le stock ne peut pas être négatif.']);
        }

        return DB::transaction(function () use ($variant, $newStock, $reason, $admin): StockMovement {
            $locked = ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $delta = $newStock - $locked->stock;

            if ($delta === 0) {
                throw ValidationException::withMessages(['stock' => 'La quantité est déjà de '.$newStock.'.']);
            }

            return $this->apply($locked, $delta, StockMovementType::Adjustment, reason: $reason, admin: $admin);
        });
    }

    /** Stock saisi à la création de la déclinaison (premier mouvement). */
    public function recordInitial(ProductVariant $variant): StockMovement
    {
        // Appelée depuis l'événement `created` : à ce stade Eloquent voit
        // encore `stock` comme modifié, d'où le drapeau pour la sauvegarde
        // éventuelle de `low_stock_notified_at`.
        self::$writing++;

        try {
            $movement = $this->log($variant, $variant->stock, StockMovementType::Initial, admin: $this->currentAdmin());
            $this->checkThreshold($variant);
        } finally {
            self::$writing--;
        }

        return $movement;
    }

    private function apply(
        ProductVariant $locked,
        int $delta,
        StockMovementType $type,
        ?Order $order = null,
        ?string $reason = null,
        ?Admin $admin = null,
    ): StockMovement {
        $newStock = $locked->stock + $delta;

        // Garde-fou : les ventes vérifient le stock sous verrou avant d'arriver ici.
        if ($newStock < 0) {
            throw new LogicException('Mouvement refusé : le stock deviendrait négatif.');
        }

        self::$writing++;

        try {
            $locked->forceFill(['stock' => $newStock])->save();
        } finally {
            self::$writing--;
        }

        $movement = $this->log($locked, $delta, $type, $order, $reason, $admin ?? $this->currentAdmin());
        $this->checkThreshold($locked);

        return $movement;
    }

    private function log(ProductVariant $variant, int $quantity, StockMovementType $type, ?Order $order = null, ?string $reason = null, ?Admin $admin = null): StockMovement
    {
        return StockMovement::create([
            'product_variant_id' => $variant->id,
            'sku' => $variant->sku,
            'label' => $variant->label(),
            'type' => $type,
            'quantity' => $quantity,
            'stock_after' => $variant->stock,
            'reason' => $reason,
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'order_id' => $order?->id,
        ]);
    }

    private function checkThreshold(ProductVariant $variant): void
    {
        $low = $variant->isLowStock();

        if ($low && $variant->low_stock_notified_at === null) {
            $variant->forceFill(['low_stock_notified_at' => now()])->save();

            // Après commit seulement : une vente annulée par rollback ne doit
            // jamais avoir alerté.
            DB::afterCommit(function () use ($variant): void {
                $recipients = $this->stockRecipients();

                if ($recipients->isNotEmpty()) {
                    Mail::to($recipients)->queue(new LowStockAlert($variant->fresh('product')));
                }
            });
        } elseif (! $low && $variant->low_stock_notified_at !== null) {
            $variant->forceFill(['low_stock_notified_at' => null])->save();
        }
    }

    /**
     * Admins actifs ayant la permission Stock (admin complet + gestionnaire).
     * Une alerte ne doit jamais faire échouer une vente : si les permissions
     * ne sont pas encore en base (seeder non lancé), personne n'est alerté.
     *
     * @return Collection<int, Admin>
     */
    private function stockRecipients(): Collection
    {
        try {
            return Admin::permission(AdminPermission::Stock->value)->where('is_active', true)->get();
        } catch (PermissionDoesNotExist) {
            return new Collection;
        }
    }

    private function currentAdmin(): ?Admin
    {
        $admin = Auth::guard('admin')->user();

        return $admin instanceof Admin ? $admin : null;
    }
}

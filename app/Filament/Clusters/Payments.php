<?php

namespace App\Filament\Clusters;

use App\Enums\Order\PaymentEventType;
use App\Models\Order\PaymentEvent;
use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

/**
 * Entrée « Paiements » du menu : la liste des paiements en ligne et, en
 * second onglet, les messages suspects bloqués.
 */
class Payments extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'Paiements';

    protected static ?string $clusterBreadcrumb = 'Paiements';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'paiements';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /** Accès par URL directe aligné sur le menu (permission Paiements). */
    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }

    /** Alerte : messages suspects des 7 derniers jours. */
    public static function getNavigationBadge(): ?string
    {
        $suspicious = PaymentEvent::query()
            ->where('type', PaymentEventType::SuspiciousNotification)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return $suspicious > 0 ? (string) $suspicious : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Messages suspects bloqués ces 7 derniers jours';
    }
}

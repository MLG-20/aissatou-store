<?php

namespace App\Filament\Clusters;

use App\Filament\Resources\Catalogue\InventoryResource;
use Filament\Clusters\Cluster;
use Filament\Pages\SubNavigationPosition;

/**
 * Entrée « Suivi du stock » du menu : l'inventaire (une fiche par
 * déclinaison, avec son historique) et, en second onglet, tous les
 * mouvements de stock.
 */
class Stock extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Stock';

    protected static ?string $navigationLabel = 'Suivi du stock';

    protected static ?string $clusterBreadcrumb = 'Stock';

    protected static ?string $slug = 'stock';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /** Accès par URL directe aligné sur le menu (permission Stock). */
    public static function canAccess(): bool
    {
        return static::canAccessClusteredComponents();
    }

    public static function getNavigationBadge(): ?string
    {
        return InventoryResource::getNavigationBadge();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return InventoryResource::getNavigationBadgeTooltip();
    }
}

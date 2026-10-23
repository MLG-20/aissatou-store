<?php

namespace App\Filament\Pages;

use App\Enums\Admin\AdminPermission;
use App\Models\Admin;
use App\Support\StatsPeriod;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Tableau de bord (Phase 6D). Accessible à tout admin (page d'accueil du
 * back-office), mais les statistiques — données business sensibles — ne
 * s'affichent qu'avec la permission Statistiques (chaque widget revérifie).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Tableau de bord';

    public function filtersForm(Form $form): Form
    {
        return $form->schema(self::canSeeStatistics() ? [
            Select::make('period')
                ->label('Période')
                ->options(StatsPeriod::options())
                ->default(StatsPeriod::DEFAULT)
                ->selectablePlaceholder(false),
        ] : []);
    }

    public static function canSeeStatistics(): bool
    {
        $admin = Filament::auth()->user();

        return $admin instanceof Admin && $admin->hasAdminPermission(AdminPermission::Statistics);
    }
}

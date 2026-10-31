<?php

namespace App\Filament\Pages;

use App\Support\AdminGuides;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * Aide du back-office : « Que voulez-vous faire ? », puis une visite guidée
 * sur le vrai écran. Chacun ne voit que ce que son rôle lui permet de faire.
 */
class Help extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Aide';

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'aide';

    protected static ?string $title = 'Que voulez-vous faire ?';

    protected static string $view = 'filament.pages.help';

    /** @return array<string, list<array{title: string, description: string, icon: string, url: string}>> */
    public function guides(): array
    {
        return AdminGuides::for(Filament::auth()->user());
    }
}

<?php

namespace App\Filament\Resources\Content\HeroSlideResource\Pages;

use App\Filament\Resources\Content\HeroSlideResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageHeroSlides extends ManageRecords
{
    protected static string $resource = HeroSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->modalWidth('5xl'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Content\ContactMessageResource\Pages;

use App\Filament\Resources\Content\ContactMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;
}

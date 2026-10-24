<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Str;

/**
 * Titres en français : majuscule au premier mot seulement (« Journal
 * d'activité », « Codes promo »), là où Filament met une majuscule à chaque
 * mot (« Journal D'activité »).
 */
trait HasSentenceCaseTitles
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}

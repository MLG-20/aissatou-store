<?php

namespace App\Models\Content;

use App\Enums\Content\CookieCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Une ligne du registre des cookies : ce que le site dépose, pourquoi, pour
 * combien de temps. Source unique de la page « Politique cookies » et du
 * bandeau de consentement.
 */
#[Fillable(['name', 'provider', 'category', 'purpose', 'duration', 'is_active', 'position'])]
class CookieDeclaration extends Model
{
    protected function casts(): array
    {
        return [
            'category' => CookieCategory::class,
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Un cookie ajouté en admin se range en fin de liste.
        static::creating(function (CookieDeclaration $cookie): void {
            $cookie->position ??= (int) static::query()->max('position') + 1;
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

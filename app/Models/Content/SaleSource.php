<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Une provenance de ventes (TikTok, Statuts WhatsApp, une influenceuse…) et
 * son lien de suivi. Le code figure dans les liens déjà publiés : il ne change
 * pas après la création.
 */
#[Fillable(['name', 'code', 'is_active'])]
class SaleSource extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function link(): string
    {
        return url('/').'/?src='.$this->code;
    }
}

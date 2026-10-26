<?php

namespace App\Models\Content;

use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Point posé sur la photo d'un look. `x` et `y` sont des pourcentages de la
 * photo (0 = bord gauche/haut, 100 = bord droit/bas).
 */
#[Fillable(['look_id', 'product_id', 'x', 'y', 'position'])]
class LookHotspot extends Model
{
    protected function casts(): array
    {
        return [
            'x' => 'float',
            'y' => 'float',
            'position' => 'integer',
        ];
    }

    public function look(): BelongsTo
    {
        return $this->belongsTo(Look::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

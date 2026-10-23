<?php

namespace App\Services;

use App\Models\Order\PromoCode;

/** Résultat de l'évaluation d'un code promo sur un panier. */
final class PromoQuote
{
    public function __construct(
        public readonly PromoCode $code,
        public readonly float $discount,
        public readonly float $eligibleSubtotal,
    ) {}
}

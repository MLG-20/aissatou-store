<?php

namespace App\Exceptions;

use RuntimeException;

class WishlistException extends RuntimeException
{
    public static function productUnavailable(): self
    {
        return new self("Ce produit n'est plus disponible.");
    }
}

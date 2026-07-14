<?php

namespace App\Support\Cart;

use RuntimeException;

class ProductUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Este producto no esta disponible en la tienda.');
    }
}

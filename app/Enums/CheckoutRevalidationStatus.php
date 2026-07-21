<?php

namespace App\Enums;

enum CheckoutRevalidationStatus: string
{
    case Unchanged = 'unchanged';
    case Changed = 'changed';
    case Blocked = 'blocked';
}

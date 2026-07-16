<?php

namespace App\Models\Concerns;

use LogicException;

trait Immutable
{
    public static function bootImmutable(): void
    {
        static::updating(function (): never {
            throw new LogicException('Los registros historicos no se pueden modificar.');
        });

        static::deleting(function (): never {
            throw new LogicException('Los registros historicos no se pueden eliminar.');
        });
    }
}

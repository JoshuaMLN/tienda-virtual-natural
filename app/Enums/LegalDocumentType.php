<?php

namespace App\Enums;

enum LegalDocumentType: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';

    public function label(): string
    {
        return match ($this) {
            self::Terms => 'Terminos y condiciones',
            self::Privacy => 'Politica de privacidad',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::Terms => 'shop.terms',
            self::Privacy => 'shop.privacy',
        };
    }
}

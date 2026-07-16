<?php

namespace App\Enums;

enum FiscalIdentityDocumentType: string
{
    case Dni = 'dni';
    case ForeignerCard = 'foreigner_card';
    case Passport = 'passport';
    case Ruc = 'ruc';

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::ForeignerCard => 'Carnet de extranjeria',
            self::Passport => 'Pasaporte',
            self::Ruc => 'RUC',
        };
    }
}

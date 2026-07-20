<?php

namespace App\Support\Fiscal;

use App\Enums\FiscalIdentityDocumentType;
use App\Support\Legal\PeruvianRuc;

final class FiscalIdentityDocument
{
    public static function normalize(FiscalIdentityDocumentType $type, mixed $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return match ($type) {
            FiscalIdentityDocumentType::Dni,
            FiscalIdentityDocumentType::Ruc => (string) preg_replace('/\D+/', '', $normalized),
            FiscalIdentityDocumentType::ForeignerCard,
            FiscalIdentityDocumentType::Passport => (string) preg_replace('/[^A-Z0-9]+/', '', $normalized),
        };
    }

    public static function isValid(FiscalIdentityDocumentType $type, mixed $value): bool
    {
        $document = self::normalize($type, $value);

        return match ($type) {
            FiscalIdentityDocumentType::Dni => preg_match('/^\d{8}$/', $document) === 1,
            FiscalIdentityDocumentType::Ruc => PeruvianRuc::isValid($document),
            FiscalIdentityDocumentType::ForeignerCard => preg_match('/^[A-Z0-9]{9,12}$/', $document) === 1,
            FiscalIdentityDocumentType::Passport => preg_match('/^[A-Z0-9]{6,12}$/', $document) === 1,
        };
    }

    public static function invalidMessage(FiscalIdentityDocumentType $type): string
    {
        return match ($type) {
            FiscalIdentityDocumentType::Dni => 'Ingresa un DNI valido de 8 digitos.',
            FiscalIdentityDocumentType::Ruc => 'Ingresa un RUC peruano valido de 11 digitos.',
            FiscalIdentityDocumentType::ForeignerCard => 'Ingresa un carnet de extranjeria valido de 9 a 12 caracteres.',
            FiscalIdentityDocumentType::Passport => 'Ingresa un pasaporte valido de 6 a 12 caracteres.',
        };
    }
}

<?php

namespace Tests\Unit\Fiscal;

use App\Enums\FiscalIdentityDocumentType;
use App\Support\Fiscal\FiscalIdentityDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FiscalIdentityDocumentTest extends TestCase
{
    #[DataProvider('validDocuments')]
    public function test_it_normalizes_and_accepts_supported_documents(
        FiscalIdentityDocumentType $type,
        string $input,
        string $expected,
    ): void {
        $this->assertSame($expected, FiscalIdentityDocument::normalize($type, $input));
        $this->assertTrue(FiscalIdentityDocument::isValid($type, $input));
    }

    public static function validDocuments(): array
    {
        return [
            'dni' => [FiscalIdentityDocumentType::Dni, '12 345 678', '12345678'],
            'ruc' => [FiscalIdentityDocumentType::Ruc, '20-13131295-5', '20131312955'],
            'foreigner card' => [FiscalIdentityDocumentType::ForeignerCard, 'ce-001234567', 'CE001234567'],
            'passport' => [FiscalIdentityDocumentType::Passport, 'pa-123456', 'PA123456'],
        ];
    }

    #[DataProvider('invalidDocuments')]
    public function test_it_rejects_invalid_supported_documents(
        FiscalIdentityDocumentType $type,
        string $input,
    ): void {
        $this->assertFalse(FiscalIdentityDocument::isValid($type, $input));
    }

    public static function invalidDocuments(): array
    {
        return [
            'short dni' => [FiscalIdentityDocumentType::Dni, '1234567'],
            'invalid ruc checksum' => [FiscalIdentityDocumentType::Ruc, '20131312954'],
            'short foreigner card' => [FiscalIdentityDocumentType::ForeignerCard, 'CE123'],
            'short passport' => [FiscalIdentityDocumentType::Passport, 'AB12'],
        ];
    }
}

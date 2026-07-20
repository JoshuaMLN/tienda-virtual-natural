<?php

namespace App\Support\Checkout;

use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Support\Fiscal\FiscalIdentityDocument;
use InvalidArgumentException;
use ValueError;

final readonly class CheckoutFiscalData
{
    public function __construct(
        public FiscalDocumentType $documentType,
        public FiscalIdentityDocumentType $identityDocumentType,
        public string $identityDocumentNumber,
        public ?string $firstNames,
        public ?string $lastNames,
        public ?string $businessName,
        public ?string $fiscalAddress,
        public string $email,
    ) {}

    /** @param array<string, mixed> $attributes */
    public static function fromValidated(array $attributes): self
    {
        $fiscal = new self(
            documentType: $attributes['fiscal_document_type'],
            identityDocumentType: $attributes['fiscal_identity_document_type'],
            identityDocumentNumber: $attributes['fiscal_identity_document_number'],
            firstNames: $attributes['fiscal_first_names'],
            lastNames: $attributes['fiscal_last_names'],
            businessName: $attributes['fiscal_business_name'],
            fiscalAddress: $attributes['fiscal_address'],
            email: $attributes['fiscal_email'],
        );

        if (! $fiscal->isValid()) {
            throw new InvalidArgumentException('Los datos fiscales no cumplen las reglas del comprobante.');
        }

        return $fiscal;
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): ?self
    {
        if (! is_array($data) || (int) ($data['version'] ?? 0) !== 1) {
            return null;
        }

        try {
            $documentType = FiscalDocumentType::from((string) ($data['document_type'] ?? ''));
            $identityType = FiscalIdentityDocumentType::from((string) ($data['identity_document_type'] ?? ''));
        } catch (ValueError) {
            return null;
        }

        $value = new self(
            documentType: $documentType,
            identityDocumentType: $identityType,
            identityDocumentNumber: (string) ($data['identity_document_number'] ?? ''),
            firstNames: self::nullableString($data['first_names'] ?? null),
            lastNames: self::nullableString($data['last_names'] ?? null),
            businessName: self::nullableString($data['business_name'] ?? null),
            fiscalAddress: self::nullableString($data['fiscal_address'] ?? null),
            email: (string) ($data['email'] ?? ''),
        );

        return $value->isValid() ? $value : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => 1,
            'document_type' => $this->documentType->value,
            'identity_document_type' => $this->identityDocumentType->value,
            'identity_document_number' => $this->identityDocumentNumber,
            'first_names' => $this->firstNames,
            'last_names' => $this->lastNames,
            'business_name' => $this->businessName,
            'fiscal_address' => $this->fiscalAddress,
            'email' => $this->email,
        ];
    }

    public function displayName(): string
    {
        return $this->documentType === FiscalDocumentType::Invoice
            ? (string) $this->businessName
            : trim($this->firstNames.' '.$this->lastNames);
    }

    public function isValid(): bool
    {
        if (
            ! $this->documentType->isSaleDocument()
            || ! FiscalIdentityDocument::isValid($this->identityDocumentType, $this->identityDocumentNumber)
            || ! filter_var($this->email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($this->email) > 255
        ) {
            return false;
        }

        if ($this->documentType === FiscalDocumentType::Invoice) {
            return $this->identityDocumentType === FiscalIdentityDocumentType::Ruc
                && self::filledWithin($this->businessName, 200)
                && self::filledWithin($this->fiscalAddress, 255)
                && $this->firstNames === null
                && $this->lastNames === null;
        }

        return $this->identityDocumentType !== FiscalIdentityDocumentType::Ruc
            && self::filledWithin($this->firstNames, 120)
            && self::filledWithin($this->lastNames, 120)
            && $this->businessName === null
            && $this->fiscalAddress === null;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function filledWithin(?string $value, int $maximum): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $maximum;
    }
}

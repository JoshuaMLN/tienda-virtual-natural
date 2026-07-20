<?php

namespace App\Support\Checkout;

use App\Models\LegalDocument;

final readonly class CheckoutReviewSnapshot
{
    public function __construct(
        public int $userId,
        public string $contactName,
        public string $customerEmail,
        public string $contactPhone,
        public string $deliveryQuoteReference,
        public CheckoutFiscalData $fiscal,
        public int $termsDocumentId,
        public int $termsDocumentVersion,
        public string $termsContentFingerprint,
        public string $termsPublishedAt,
        public string $reviewedAt,
    ) {}

    public static function create(
        int $userId,
        string $contactName,
        string $customerEmail,
        string $contactPhone,
        CheckoutDeliverySnapshot $deliveryQuote,
        CheckoutFiscalData $fiscal,
        LegalDocument $terms,
    ): self {
        return new self(
            userId: $userId,
            contactName: $contactName,
            customerEmail: $customerEmail,
            contactPhone: $contactPhone,
            deliveryQuoteReference: $deliveryQuote->fingerprint(),
            fiscal: $fiscal,
            termsDocumentId: (int) $terms->getKey(),
            termsDocumentVersion: (int) $terms->version,
            termsContentFingerprint: self::contentFingerprint($terms),
            termsPublishedAt: $terms->published_at?->toAtomString() ?? '',
            reviewedAt: now()->toAtomString(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->payload(),
            'fingerprint' => $this->fingerprint(),
        ];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode(
            $this->payload(),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): ?self
    {
        if (
            ! is_array($data)
            || (int) ($data['version'] ?? 0) !== 1
            || ! is_array($data['fiscal'] ?? null)
        ) {
            return null;
        }

        $fiscal = CheckoutFiscalData::fromArray($data['fiscal']);

        if ($fiscal === null) {
            return null;
        }

        $snapshot = new self(
            userId: (int) ($data['user_id'] ?? 0),
            contactName: (string) ($data['contact_name'] ?? ''),
            customerEmail: (string) ($data['customer_email'] ?? ''),
            contactPhone: (string) ($data['contact_phone'] ?? ''),
            deliveryQuoteReference: (string) ($data['delivery_quote_reference'] ?? ''),
            fiscal: $fiscal,
            termsDocumentId: (int) ($data['terms_document_id'] ?? 0),
            termsDocumentVersion: (int) ($data['terms_document_version'] ?? 0),
            termsContentFingerprint: (string) ($data['terms_content_fingerprint'] ?? ''),
            termsPublishedAt: (string) ($data['terms_published_at'] ?? ''),
            reviewedAt: (string) ($data['reviewed_at'] ?? ''),
        );
        $fingerprint = $data['fingerprint'] ?? null;

        if (
            $snapshot->userId < 1
            || trim($snapshot->contactName) === ''
            || ! filter_var($snapshot->customerEmail, FILTER_VALIDATE_EMAIL)
            || preg_match('/^9\d{8}$/', $snapshot->contactPhone) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $snapshot->deliveryQuoteReference) !== 1
            || $snapshot->termsDocumentId < 1
            || $snapshot->termsDocumentVersion < 1
            || preg_match('/^[a-f0-9]{64}$/', $snapshot->termsContentFingerprint) !== 1
            || $snapshot->termsPublishedAt === ''
            || $snapshot->reviewedAt === ''
            || ! is_string($fingerprint)
            || ! hash_equals($snapshot->fingerprint(), $fingerprint)
        ) {
            return null;
        }

        return $snapshot;
    }

    public function accepts(LegalDocument $terms): bool
    {
        return $this->termsDocumentId === (int) $terms->getKey()
            && $this->termsDocumentVersion === (int) $terms->version
            && hash_equals($this->termsContentFingerprint, self::contentFingerprint($terms));
    }

    private static function contentFingerprint(LegalDocument $terms): string
    {
        return hash('sha256', json_encode([
            'id' => (int) $terms->getKey(),
            'type' => $terms->type->value,
            'version' => (int) $terms->version,
            'title' => $terms->title,
            'body' => $terms->body,
            'published_at' => $terms->published_at?->toAtomString(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'version' => 1,
            'user_id' => $this->userId,
            'contact_name' => $this->contactName,
            'customer_email' => $this->customerEmail,
            'contact_phone' => $this->contactPhone,
            'delivery_quote_reference' => $this->deliveryQuoteReference,
            'fiscal' => $this->fiscal->toArray(),
            'terms_document_id' => $this->termsDocumentId,
            'terms_document_version' => $this->termsDocumentVersion,
            'terms_content_fingerprint' => $this->termsContentFingerprint,
            'terms_published_at' => $this->termsPublishedAt,
            'reviewed_at' => $this->reviewedAt,
        ];
    }
}

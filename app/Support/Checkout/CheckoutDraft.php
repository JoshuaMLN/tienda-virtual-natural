<?php

namespace App\Support\Checkout;

final readonly class CheckoutDraft
{
    public function __construct(
        public int $userId,
        public string $contactName,
        public string $contactPhone,
        public int $addressId,
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'contact_name' => $this->contactName,
            'contact_phone' => $this->contactPhone,
            'address_id' => $this->addressId,
        ];
    }
}

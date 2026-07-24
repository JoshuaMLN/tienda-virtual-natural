<?php

namespace App\Support\Orders\Notifications;

final readonly class OrderEmailThumbnail
{
    public function __construct(
        public string $contents,
        public string $fingerprint,
        public string $filename,
    ) {}

    public function bytes(): int
    {
        return strlen($this->contents);
    }
}

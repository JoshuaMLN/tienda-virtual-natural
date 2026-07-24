<?php

namespace App\Support\Orders\Notifications;

final readonly class OrderNotificationRecipient
{
    public function __construct(
        public string $email,
        public ?string $name,
    ) {}
}

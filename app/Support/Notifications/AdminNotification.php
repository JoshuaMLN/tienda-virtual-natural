<?php

namespace App\Support\Notifications;

class AdminNotification
{
    public function __construct(
        public readonly string $type,
        public readonly string $icon,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $url = null,
    ) {
    }
}

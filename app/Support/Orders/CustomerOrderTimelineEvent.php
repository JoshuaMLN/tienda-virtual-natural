<?php

namespace App\Support\Orders;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final readonly class CustomerOrderTimelineEvent
{
    public CarbonImmutable $occurredAt;

    public function __construct(
        public string $title,
        public string $description,
        public string $icon,
        public string $tone,
        DateTimeInterface $occurredAt,
    ) {
        $this->occurredAt = CarbonImmutable::instance($occurredAt);
    }
}

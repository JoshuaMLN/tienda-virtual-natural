<?php

namespace App\Support\Notifications;

class AdminNotificationService
{
    /** @var array<int, class-string> */
    private array $providers = [];

    public function registerProvider(string $providerClass): void
    {
        $this->providers[] = $providerClass;
    }

    /**
     * @return AdminNotification[]
     */
    public function getAll(): array
    {
        $notifications = [];

        foreach ($this->providers as $providerClass) {
            $provider = app($providerClass);

            if (method_exists($provider, 'getNotifications')) {
                $notifications = array_merge($notifications, $provider->getNotifications());
            }
        }

        return $notifications;
    }

    public function getCount(): int
    {
        return count($this->getAll());
    }
}

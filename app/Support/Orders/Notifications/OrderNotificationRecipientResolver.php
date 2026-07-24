<?php

namespace App\Support\Orders\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

class OrderNotificationRecipientResolver
{
    /** @return list<OrderNotificationRecipient> */
    public function resolve(Order $order): array
    {
        $recipients = [];
        $this->add(
            $recipients,
            $order->customer_email,
            $order->customer_name,
        );

        if ($order->user_id === null) {
            return array_values($recipients);
        }

        $user = User::query()->find($order->user_id);

        if ($user?->hasVerifiedEmail() === true) {
            $this->add($recipients, $user->email, $user->name);
        }

        return array_values($recipients);
    }

    /**
     * @param  array<string, OrderNotificationRecipient>  $recipients
     */
    private function add(array &$recipients, mixed $email, mixed $name): void
    {
        if (! is_string($email)) {
            return;
        }

        $normalizedEmail = Str::lower(trim($email));

        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        if (isset($recipients[$normalizedEmail])) {
            return;
        }

        $normalizedName = is_string($name) ? trim($name) : null;
        $recipients[$normalizedEmail] = new OrderNotificationRecipient(
            $normalizedEmail,
            $normalizedName !== '' ? $normalizedName : null,
        );
    }
}

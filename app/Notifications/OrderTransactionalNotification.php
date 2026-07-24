<?php

namespace App\Notifications;

use App\Models\OrderNotificationDelivery;
use App\Support\Orders\Notifications\OrderTransactionalEmailPresenter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderTransactionalNotification extends Notification
{
    public function __construct(
        private readonly OrderNotificationDelivery $delivery,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = app(OrderTransactionalEmailPresenter::class)->present($this->delivery);

        return (new MailMessage)
            ->subject($data['subject'])
            ->view([
                'html' => 'emails.orders.transactional',
                'text' => 'emails.orders.transactional-text',
            ], $data);
    }
}

<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Verifica tu correo electronico | VitaNatural')
            ->greeting('Hola')
            ->line('Confirma que este correo electronico pertenece a tu cuenta de VitaNatural.')
            ->action('Verificar correo electronico', $url)
            ->line("Este enlace vencera en {$minutes} minutos.")
            ->line('Si no creaste esta cuenta, puedes ignorar este mensaje.');
    }
}

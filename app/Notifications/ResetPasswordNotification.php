<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Restablece tu contrasena | VitaNatural')
            ->greeting('Hola')
            ->line('Recibimos una solicitud para restablecer la contrasena de tu cuenta.')
            ->action('Restablecer contrasena', $url)
            ->line("Este enlace vencera en {$minutes} minutos.")
            ->line('Si no realizaste esta solicitud, puedes ignorar este mensaje.');
    }
}

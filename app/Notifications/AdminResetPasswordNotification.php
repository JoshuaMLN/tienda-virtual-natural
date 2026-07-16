<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdminResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return url(route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Restablece tu acceso administrativo | VitaNatural')
            ->greeting('Hola')
            ->line('Recibimos una solicitud para restablecer la contrasena de tu acceso administrativo.')
            ->action('Restablecer acceso', $url)
            ->line("Este enlace vencera en {$minutes} minutos.")
            ->line('Si no realizaste esta solicitud, comunicate con el responsable de la tienda.');
    }
}

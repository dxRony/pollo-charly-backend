<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : $notifiable->email;

        $resetUrl = rtrim($frontendUrl, '/') . '/reset-password?token=' . $this->token . '&email=' . urlencode($email);

        return (new MailMessage)
            ->subject('Recuperación de Contraseña - Pollo Charly')
            ->greeting('¡Hola, ' . ($notifiable->name ?? 'Usuario') . '!')
            ->line('Recibiste este correo porque se solicitó un restablecimiento de contraseña para tu cuenta en Pollo Charly.')
            ->action('Restablecer Contraseña', $resetUrl)
            ->line('Código / Token de recuperación: ' . $this->token)
            ->line('Este enlace y token de recuperación expirarán en 60 minutos.')
            ->line('Si no solicitaste este cambio, no es necesario realizar ninguna acción; tu cuenta sigue completamente segura.')
            ->salutation('Atentamente, el equipo de Pollo Charly.');
    }
}

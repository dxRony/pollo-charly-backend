<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code
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
        return (new MailMessage)
            ->subject('Código de Verificación (2FA) - Pollo Charly')
            ->greeting('¡Hola, ' . ($notifiable->name ?? 'Usuario') . '!')
            ->line('Has solicitado iniciar sesión en el sistema de Pollo Charly.')
            ->line('Tu código de verificación de 6 dígitos es:')
            ->line('# ' . $this->code)
            ->line('Este código de un solo uso expirará en 5 minutos.')
            ->line('Si no fuiste tú quien intentó iniciar sesión, te recomendamos cambiar tu contraseña de inmediato para proteger tu cuenta.')
            ->salutation('Atentamente, el equipo de Pollo Charly.');
    }
}

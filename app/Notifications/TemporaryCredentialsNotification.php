<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemporaryCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $temporaryPassword,
        public readonly bool $isReset = false,
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
            ->subject($this->isReset
                ? 'Tu contraseña fue restablecida - Pollo Charly'
                : 'Bienvenido a Pollo Charly: tus credenciales de acceso')
            ->greeting('¡Hola, '.($notifiable->name ?? 'Usuario').'!')
            ->line($this->isReset
                ? 'Un administrador restableció tu contraseña. Estas son tus nuevas credenciales temporales:'
                : 'Se creó una cuenta para ti en el sistema de Pollo Charly. Estas son tus credenciales temporales:')
            ->line('**Correo:** '.$notifiable->email)
            ->line('**Contraseña temporal:** '.$this->temporaryPassword)
            ->action('Iniciar sesión', rtrim((string) config('app.frontend_url'), '/').'/login')
            ->line('Por seguridad, el sistema te pedirá cambiar esta contraseña la primera vez que inicies sesión.')
            ->line('Si no esperabas este correo, comunícate con la administración.')
            ->salutation('Atentamente, el equipo de Pollo Charly.');
    }
}

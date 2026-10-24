<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\BuildsFrontendLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TemporaryCredentialsNotification extends Notification
{
    use BuildsFrontendLinks;
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
            ->markdown('mail.temporary-credentials', [
                'name' => $notifiable->name ?? 'Usuario',
                'email' => $notifiable->email,
                'password' => $this->temporaryPassword,
                'isReset' => $this->isReset,
                'loginUrl' => $this->frontendUrl('/login'),
            ]);
    }
}

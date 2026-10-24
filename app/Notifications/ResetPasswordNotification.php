<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Concerns\BuildsFrontendLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use BuildsFrontendLinks;
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
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : $notifiable->email;

        return (new MailMessage)
            ->subject('Recuperación de Contraseña - Pollo Charly')
            ->markdown('mail.reset-password', [
                'name' => $notifiable->name ?? 'Usuario',
                'token' => $this->token,
                'resetUrl' => $this->frontendUrl('/reset-password?token='.$this->token.'&email='.urlencode($email)),
            ]);
    }
}

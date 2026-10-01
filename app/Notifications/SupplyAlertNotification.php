<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupplyAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplyAlertNotification extends Notification
{
    use Queueable;

    /**
     * Crea una nueva instancia de la notificación de alerta de reposición.
     */
    public function __construct(
        public readonly SupplyAlert $alert
    ) {}

    /**
     * Canales de notificación por los cuales se enviará el mensaje.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Construye el mensaje de correo electrónico para la administradora.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $supply = $this->alert->supply;
        $originName = $this->alert->origin?->name === 'automatic' ? 'Automática' : 'Manual';
        $generatorName = $this->alert->user?->name ?? 'Detección automática del sistema';
        $formattedDate = $this->alert->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s');

        $mail = (new MailMessage)
            ->subject('Alerta de Reposición de Insumo - Pollo Charly')
            ->greeting('¡Hola, ' . ($notifiable->name ?? 'Administradora') . '!')
            ->line('Se ha registrado una nueva alerta de reposición para el siguiente insumo en el almacén:')
            ->line('**Insumo:** ' . ($supply?->name ?? 'N/A') . ' (' . ($supply?->code ?? 'N/A') . ')')
            ->line('**Origen de la alerta:** ' . $originName)
            ->line('**Fecha y hora:** ' . $formattedDate)
            ->line('**Generada por:** ' . $generatorName);

        if ($supply) {
            $unit = $supply->measurementUnit?->abbreviation ?? '';
            $mail->line('**Existencia actual:** ' . $supply->current_stock . ' ' . $unit)
                ->line('**Cantidad de referencia mínima:** ' . $supply->minimum_stock . ' ' . $unit);
        }

        if (! empty($this->alert->notes)) {
            $mail->line('**Observaciones:** ' . $this->alert->notes);
        }

        return $mail->line('Por favor revisa el módulo de inventario para gestionar la orden o solicitud de compra correspondiente.')
            ->salutation('Atentamente, el sistema de Pollo Charly.');
    }
}

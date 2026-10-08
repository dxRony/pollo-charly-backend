<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DeliveryIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryIncidentNotification extends Notification
{
    use Queueable;

    /**
     * Crea una nueva instancia de la notificación de incidencia en entrega.
     */
    public function __construct(
        public readonly DeliveryIncident $incident
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
     * Construye el correo electrónico de alerta para la administradora.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $supplier = $this->incident->supplier;
        $order = $this->incident->purchaseOrder;
        $type = $this->incident->type?->name ?? 'No especificado';
        $user = $this->incident->receivingUser?->name ?? 'Mesero/Cajero';
        $orderCode = $order?->code ?? 'Entrega sin orden previa';

        $mail = (new MailMessage)
            ->subject("Alerta: Incidencia en Entrega de Proveedor - {$supplier?->company_name}")
            ->greeting('¡Hola, '.($notifiable->name ?? 'Administradora').'!')
            ->line('Se ha registrado una **recepción no conforme** durante la entrega de un pedido:')
            ->line("**Proveedor:** {$supplier?->company_name}")
            ->line("**Orden de Compra:** {$orderCode}")
            ->line("**Tipo de Incidencia:** {$type}")
            ->line("**Reportado por:** {$user}")
            ->line("**Descripción:** {$this->incident->description}");

        if ($this->incident->evidence_path) {
            $mail->line("**Evidencia adjunta:** {$this->incident->evidence_path}");
        }

        return $mail->line('Por favor revisa el módulo de compras y proveedores para gestionar la corrección o reposición de productos con el proveedor.')
            ->salutation('Atentamente, el sistema de Pollo Charly.');
    }
}

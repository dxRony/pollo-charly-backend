<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DeliveryIncident;
use App\Notifications\Concerns\BuildsFrontendLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryIncidentNotification extends Notification
{
    use BuildsFrontendLinks;
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
        $rows = [
            ['label' => 'Proveedor', 'value' => $supplier?->company_name ?? 'No especificado'],
            ['label' => 'Orden de compra', 'value' => $this->incident->purchaseOrder?->code ?? 'Entrega sin orden previa'],
            ['label' => 'Tipo de incidencia', 'value' => $this->incident->type?->name ?? 'No especificado'],
            ['label' => 'Reportado por', 'value' => $this->incident->receivingUser?->name ?? 'Mesero/Cajero'],
            ['label' => 'Descripción', 'value' => (string) $this->incident->description],
        ];

        if ($this->incident->evidence_path) {
            $rows[] = ['label' => 'Evidencia adjunta', 'value' => $this->incident->evidence_path];
        }

        return (new MailMessage)
            ->subject("Alerta: Incidencia en Entrega de Proveedor - {$supplier?->company_name}")
            ->markdown('mail.delivery-incident', [
                'name' => $notifiable->name ?? 'Administradora',
                'rows' => $rows,
                'actionUrl' => $this->frontendUrl('/dashboard/purchases'),
            ]);
    }
}

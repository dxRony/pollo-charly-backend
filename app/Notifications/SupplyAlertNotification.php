<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupplyAlert;
use App\Notifications\Concerns\BuildsFrontendLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplyAlertNotification extends Notification
{
    use BuildsFrontendLinks;
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
        $generatorName = $this->alert->user?->name ?? 'Detección automática del sistema';
        $formattedDate = $this->alert->created_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s');
        $unit = $supply?->measurementUnit?->abbreviation ?? '';
        $supplyLabel = ($supply?->name ?? 'N/A').' ('.($supply?->code ?? 'N/A').')';

        if ($this->alert->inventory_movement_id !== null) {
            return $this->adjustmentMail($notifiable, $supplyLabel, $generatorName, $formattedDate, $unit);
        }

        $originName = $this->alert->origin?->name === 'automatic' ? 'Automática' : 'Manual';

        $rows = [
            ['label' => 'Insumo', 'value' => $supplyLabel],
            ['label' => 'Origen de la alerta', 'value' => $originName],
            ['label' => 'Fecha y hora', 'value' => $formattedDate],
            ['label' => 'Generada por', 'value' => $generatorName],
        ];

        if ($supply) {
            $rows[] = ['label' => 'Existencia actual', 'value' => trim($supply->current_stock.' '.$unit)];
            $rows[] = ['label' => 'Cantidad mínima de referencia', 'value' => trim($supply->minimum_stock.' '.$unit)];
        }

        if (! empty($this->alert->notes)) {
            $rows[] = ['label' => 'Observaciones', 'value' => $this->alert->notes];
        }

        return (new MailMessage)
            ->subject('Alerta de Reposición de Insumo - Pollo Charly')
            ->markdown('mail.supply-alert', [
                'name' => $notifiable->name ?? 'Administradora',
                'intro' => 'Se registró una nueva alerta de reposición para el siguiente insumo en el almacén:',
                'rows' => $rows,
                'actionText' => 'Revisar alertas de reposición',
                'actionUrl' => $this->frontendUrl('/dashboard/supply-alerts'),
                'closing' => 'Por favor revisa el módulo de inventario para gestionar la orden o solicitud de compra correspondiente.',
            ]);
    }

    private function adjustmentMail(object $notifiable, string $supplyLabel, string $generatorName, string $formattedDate, string $unit): MailMessage
    {
        $movement = $this->alert->inventoryMovement;

        $rows = [
            ['label' => 'Insumo / Producto', 'value' => $supplyLabel],
            ['label' => 'Fecha y hora', 'value' => $formattedDate],
            ['label' => 'Usuario que generó el ajuste', 'value' => $generatorName],
        ];

        if ($movement) {
            $rows[] = ['label' => 'Existencia previa', 'value' => trim($movement->previous_stock.' '.$unit)];
            $rows[] = ['label' => 'Nueva existencia solicitada', 'value' => trim($movement->new_stock.' '.$unit)];
            $rows[] = ['label' => 'Motivo del ajuste', 'value' => $movement->reason ?? 'Sin motivo'];
        } elseif (! empty($this->alert->notes)) {
            $rows[] = ['label' => 'Motivo / Detalle', 'value' => $this->alert->notes];
        }

        return (new MailMessage)
            ->subject('Solicitud de Ajuste de Inventario - Pollo Charly')
            ->markdown('mail.supply-alert', [
                'name' => $notifiable->name ?? 'Administradora',
                'intro' => 'Se registró una nueva solicitud de ajuste manual de inventario pendiente de aprobación:',
                'rows' => $rows,
                'actionText' => 'Revisar ajuste de inventario',
                'actionUrl' => $this->frontendUrl('/dashboard/inventory-movements'),
                'closing' => 'Por favor revisa el módulo de movimientos o alertas para aprobar o rechazar este ajuste.',
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderNotification extends Notification
{
    use Queueable;

    /**
     * Crea una nueva instancia de la notificación de orden de compra.
     */
    public function __construct(
        public readonly PurchaseOrder $order
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
     * Construye el mensaje de correo electrónico con la orden de compra.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $supplier = $this->order->supplier;
        $orderCode = $this->order->code;
        $totalFormatted = number_format((float) $this->order->total, 2);
        $expectedDate = $this->order->expected_date?->format('d/m/Y') ?? 'A convenir';

        $mail = (new MailMessage)
            ->subject("Orden de Compra {$orderCode} - Pollo Charly")
            ->greeting('¡Estimado(a) ' . ($supplier?->contact_name ?? $supplier?->company_name ?? 'Proveedor') . '!')
            ->line("Se ha generado una nueva orden de compra formal para **{$supplier?->company_name}**.")
            ->line("**Código de orden:** {$orderCode}")
            ->line("**Fecha prevista de entrega:** {$expectedDate}")
            ->line("**Total de la compra:** \${$totalFormatted}");

        $items = $this->order->items;
        if ($items && $items->isNotEmpty()) {
            $mail->line('---')
                ->line('**Detalle de productos solicitados:**');
            foreach ($items as $item) {
                $qty = (float) $item->ordered_quantity;
                $unit = $item->supply?->measurementUnit?->abbreviation ?? 'uds';
                $price = number_format((float) $item->unit_price, 2);
                $subtotal = number_format((float) $item->subtotal, 2);
                $mail->line("- {$item->supply?->name}: {$qty} {$unit} a \${$price} = \${$subtotal}");
            }
        }

        return $mail->line('Por favor confirme la recepción de esta orden y la fecha estimada de entrega.')
            ->salutation('Atentamente, Pollo Charly.');
    }
}

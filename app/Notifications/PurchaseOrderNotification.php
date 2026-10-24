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

        $items = $this->order->items->map(fn ($item) => [
            // La barra vertical separa columnas en la tabla Markdown.
            'name' => str_replace('|', '/', (string) $item->supply?->name),
            'quantity' => (float) $item->ordered_quantity.' '.($item->supply?->measurementUnit?->abbreviation ?? 'uds'),
            'price' => $this->money($item->unit_price),
            'subtotal' => $this->money($item->subtotal),
        ])->all();

        return (new MailMessage)
            ->subject("Orden de Compra {$orderCode} - Pollo Charly")
            ->markdown('mail.purchase-order', [
                'contactName' => $supplier?->contact_name ?? $supplier?->company_name ?? 'Proveedor',
                'companyName' => $supplier?->company_name,
                'rows' => [
                    ['label' => 'Código de orden', 'value' => $orderCode],
                    ['label' => 'Fecha prevista de entrega', 'value' => $this->order->expected_date?->format('d/m/Y') ?? 'A convenir'],
                    ['label' => 'Total de la compra', 'value' => $this->money($this->order->total)],
                ],
                'items' => $items,
            ]);
    }

    private function money(mixed $amount): string
    {
        return 'Q'.number_format((float) $amount, 2);
    }
}

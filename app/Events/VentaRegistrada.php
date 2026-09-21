<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VentaRegistrada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Crea una nueva instancia del evento de venta registrada.
     */
    public function __construct(
        public readonly Sale $sale
    ) {}

    /**
     * Canal privado por el cual se transmite el evento.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('cocina'),
        ];
    }

    /**
     * Nombre público del evento para la escucha en frontend vía Laravel Echo.
     */
    public function broadcastAs(): string
    {
        return 'VentaRegistrada';
    }

    /**
     * Datos transmitidos en el cuerpo del evento WebSocket.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => 'Venta registrada y comanda cerrada exitosamente.',
            'sale_id' => $this->sale->id,
            'order_id' => $this->sale->order_id,
            'receipt_number' => $this->sale->receipt_number,
            'total' => (float) $this->sale->total,
            'sale' => (new SaleResource($this->sale->loadMissing([
                'order.restaurantTable.status',
                'order.items.dish',
                'cashier.role',
                'receiptType',
                'paymentMethod',
                'status',
            ])))->resolve(),
        ];
    }
}

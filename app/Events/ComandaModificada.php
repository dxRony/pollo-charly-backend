<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComandaModificada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Crea una nueva instancia del evento de comanda modificada.
     *
     * @param Order $order Instancia de la comanda con sus cambios aplicados.
     * @param array<string, mixed> $details Resumen descriptivo de los platillos agregados o eliminados.
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $details = []
    ) {}

    /**
     * Canal privado por el cual se transmite el evento a la pantalla de cocina.
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
        return 'ComandaModificada';
    }

    /**
     * Datos transmitidos en el cuerpo del evento WebSocket.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => 'Comanda modificada en curso.',
            'order_id' => $this->order->id,
            'code' => $this->order->code,
            'details' => $this->details,
            'order' => (new OrderResource($this->order->loadMissing([
                'restaurantTable.status',
                'waiter.role',
                'type',
                'status',
                'items.dish.category',
                'items.status',
                'items.complements.complement',
            ])))->resolve(),
        ];
    }
}

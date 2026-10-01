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

class ComandaEstadoActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Crea una nueva instancia del evento de actualización de estado de comanda.
     *
     * @param Order $order Instancia de la comanda con el nuevo estado aplicado.
     * @param string $previousStatus Nombre del estado anterior.
     * @param string $newStatus Nombre del nuevo estado.
     */
    public function __construct(
        public readonly Order $order,
        public readonly string $previousStatus,
        public readonly string $newStatus
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
        return 'ComandaEstadoActualizado';
    }

    /**
     * Datos transmitidos en el cuerpo del evento WebSocket.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => "Estado de comanda actualizado a '{$this->newStatus}'.",
            'order_id' => $this->order->id,
            'code' => $this->order->code,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
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

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

class ComandaLista implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Crea una nueva instancia del evento de comanda lista para entregar.
     *
     * @param Order $order Instancia de la comanda en estado lista.
     */
    public function __construct(
        public readonly Order $order
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
        return 'ComandaLista';
    }

    /**
     * Datos transmitidos en el cuerpo del evento WebSocket para notificar al personal de sala.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $tableNumber = $this->order->restaurantTable?->number !== null
            ? "Mesa #{$this->order->restaurantTable->number}"
            : 'Para llevar';

        return [
            'message' => "¡Comanda {$this->order->code} lista para entregar! ({$tableNumber})",
            'order_id' => $this->order->id,
            'code' => $this->order->code,
            'table' => $tableNumber,
            'waiter_user_id' => $this->order->waiter_user_id,
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

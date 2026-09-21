<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SaleResource',
    title: 'Sale Resource',
    description: 'Registro de venta comercial y comprobante de pago de una comanda',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único de la venta', type: 'integer', example: 1),
        new OA\Property(property: 'order_id', description: 'Identificador de la comanda asociada', type: 'integer', example: 1),
        new OA\Property(property: 'order', ref: '#/components/schemas/OrderResource', nullable: true),
        new OA\Property(property: 'cashier_user_id', description: 'ID del mesero o cajero que registró la venta', type: 'integer', example: 2),
        new OA\Property(property: 'cashier', description: 'Información del cajero responsable', type: 'object', nullable: true),
        new OA\Property(property: 'receipt_type_id', description: 'ID del tipo de comprobante emitido', type: 'integer', example: 1),
        new OA\Property(property: 'receipt_type', description: 'Nombre del tipo de comprobante (ticket, factura)', type: 'string', example: 'ticket'),
        new OA\Property(property: 'payment_method_id', description: 'ID del método de pago utilizado', type: 'integer', example: 1),
        new OA\Property(property: 'payment_method', description: 'Nombre del método de pago (efectivo, tarjeta, transferencia)', type: 'string', example: 'efectivo'),
        new OA\Property(property: 'sale_status_id', description: 'ID del estado de la venta', type: 'integer', example: 1),
        new OA\Property(property: 'sale_status', description: 'Nombre del estado de la venta (completada, anulada)', type: 'string', example: 'completada'),
        new OA\Property(property: 'receipt_number', description: 'Número único de comprobante interno emitido', type: 'string', example: 'VTA-20260921-0001'),
        new OA\Property(property: 'subtotal', description: 'Subtotal acumulado de la comanda', type: 'number', format: 'float', example: 85.00),
        new OA\Property(property: 'discount', description: 'Descuento aplicado a la venta', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'tax', description: 'Impuesto aplicado a la venta', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'total', description: 'Total definitivo a pagar por la venta', type: 'number', format: 'float', example: 85.00),
        new OA\Property(property: 'received_amount', description: 'Monto recibido del cliente', type: 'number', format: 'float', example: 100.00),
        new OA\Property(property: 'change_amount', description: 'Vuelto devuelto al cliente', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'created_at', description: 'Fecha y hora de registro de la venta', type: 'string', format: 'date-time', example: '2026-09-21T12:00:00.000000Z'),
        new OA\Property(property: 'updated_at', description: 'Fecha y hora de última modificación', type: 'string', format: 'date-time', example: '2026-09-21T12:00:00.000000Z'),
    ]
)]
class SaleResource extends JsonResource
{
    /**
     * Transforma la venta a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order' => $this->relationLoaded('order') && $this->order
                ? new OrderResource($this->order)
                : null,
            'cashier_user_id' => $this->cashier_user_id,
            'cashier' => $this->relationLoaded('cashier') && $this->cashier
                ? [
                    'id' => $this->cashier->id,
                    'name' => $this->cashier->name,
                    'email' => $this->cashier->email,
                ]
                : null,
            'receipt_type_id' => $this->receipt_type_id,
            'receipt_type' => $this->relationLoaded('receiptType') && $this->receiptType
                ? $this->receiptType->name
                : null,
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => $this->relationLoaded('paymentMethod') && $this->paymentMethod
                ? $this->paymentMethod->name
                : null,
            'sale_status_id' => $this->sale_status_id,
            'sale_status' => $this->relationLoaded('status') && $this->status
                ? $this->status->name
                : null,
            'receipt_number' => $this->receipt_number,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'received_amount' => (float) $this->received_amount,
            'change_amount' => (float) $this->change_amount,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

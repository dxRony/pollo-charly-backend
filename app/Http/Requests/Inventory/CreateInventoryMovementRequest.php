<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Models\InventoryMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateInventoryMovementRequest',
    title: 'Create Inventory Movement Request',
    description: 'Datos necesarios para registrar un movimiento de inventario (compra, salida, merma o ajuste)',
    required: ['supply_id'],
    example: [
        'supply_id' => 1,
        'type' => 'compra',
        'quantity' => 25.50,
        'reason' => 'Ingreso de mercadería por factura F-1234',
    ],
    properties: [
        new OA\Property(property: 'supply_id', description: 'ID del producto o insumo de almacén', type: 'integer', example: 1),
        new OA\Property(property: 'type', description: 'Tipo de movimiento (compra, salida, merma, ajuste, compra_entrada, consumo_venta, merma_dano, ajuste_inventario)', type: 'string', example: 'compra', nullable: true),
        new OA\Property(property: 'inventory_movement_type_id', description: 'ID del tipo de movimiento en base de datos', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'quantity', description: 'Cantidad del movimiento (o cantidad física corregida en ajustes)', type: 'number', format: 'float', example: 25.50),
        new OA\Property(property: 'new_stock', description: 'Nueva existencia física en caso de ajuste de inventario (opcional si se provee quantity)', type: 'number', format: 'float', nullable: true, example: 50.00),
        new OA\Property(property: 'reason', description: 'Motivo u observación del movimiento (obligatorio para merma y ajuste)', type: 'string', maxLength: 1000, nullable: true, example: 'Merma por producto en mal estado'),
        new OA\Property(property: 'order_id', description: 'ID de la comanda relacionada (opcional)', type: 'integer', nullable: true),
        new OA\Property(property: 'order_item_id', description: 'ID del ítem de comanda relacionado (opcional)', type: 'integer', nullable: true),
        new OA\Property(property: 'purchase_order_id', description: 'ID de la orden de compra relacionada (opcional)', type: 'integer', nullable: true),
    ]
)]
class CreateInventoryMovementRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación aplicadas a la solicitud.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supply_id' => ['required', 'integer', 'exists:supplies,id'],
            'type' => ['nullable', 'string', 'in:compra,salida,merma,ajuste,compra_entrada,consumo_venta,merma_dano,ajuste_inventario,cancelacion_pedido'],
            'inventory_movement_type_id' => ['nullable', 'integer', 'exists:inventory_movement_types,id'],
            'quantity' => ['nullable', 'numeric'],
            'new_stock' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supply_id.required' => 'El producto o insumo es obligatorio.',
            'supply_id.integer' => 'El identificador del insumo debe ser un número entero.',
            'supply_id.exists' => 'El producto o insumo seleccionado no existe.',
            'type.in' => 'El tipo de movimiento especificado no es válido.',
            'inventory_movement_type_id.exists' => 'El tipo de movimiento seleccionado no existe.',
            'quantity.numeric' => 'La cantidad debe ser un valor numérico.',
            'new_stock.numeric' => 'La nueva existencia debe ser un valor numérico.',
            'new_stock.min' => 'La nueva existencia no puede ser negativa.',
            'reason.max' => 'El motivo u observación no puede exceder los 1000 caracteres.',
            'order_id.exists' => 'La comanda especificada no existe.',
            'order_item_id.exists' => 'El ítem de comanda especificado no existe.',
            'purchase_order_id.exists' => 'La orden de compra especificada no existe.',
        ];
    }

    /**
     * Validador adicional para reglas condicionales según el tipo de movimiento.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $typeInput = $this->input('type');
            $typeIdInput = $this->input('inventory_movement_type_id');

            if (empty($typeInput) && empty($typeIdInput)) {
                $v->errors()->add('type', 'Debe especificar el tipo de movimiento o el ID del tipo de movimiento.');
                return;
            }

            $resolvedType = $this->getResolvedTypeName();

            if (! $resolvedType) {
                $v->errors()->add('type', 'El tipo de movimiento especificado no es válido.');
                return;
            }

            $quantity = $this->input('quantity');
            $newStock = $this->input('new_stock');
            $reason = trim((string) $this->input('reason', ''));

            // Validaciones según el tipo de movimiento resuelto
            if ($resolvedType === InventoryMovementType::AJUSTE_INVENTARIO) {
                if ($quantity === null && $newStock === null) {
                    $v->errors()->add('quantity', 'Debe indicar la cantidad o la existencia corregida para el ajuste.');
                }
                if ($quantity !== null && (float) $quantity < 0) {
                    $v->errors()->add('quantity', 'La cantidad para el ajuste no puede ser negativa.');
                }
                if (empty($reason)) {
                    $v->errors()->add('reason', 'El motivo es obligatorio al solicitar un ajuste de inventario.');
                }
            } else {
                if ($quantity === null) {
                    $v->errors()->add('quantity', 'La cantidad del movimiento es obligatoria.');
                } elseif ((float) $quantity <= 0) {
                    $v->errors()->add('quantity', 'La cantidad debe ser un valor positivo mayor a 0.');
                }

                if ($resolvedType === InventoryMovementType::MERMA_DANO && empty($reason)) {
                    $v->errors()->add('reason', 'El motivo es obligatorio al registrar una merma.');
                }
            }
        });
    }

    /**
     * Resuelve el nombre canónico del tipo de movimiento.
     */
    public function getResolvedTypeName(): ?string
    {
        $typeInput = $this->input('type');

        if (! empty($typeInput)) {
            $map = [
                'compra' => InventoryMovementType::COMPRA_ENTRADA,
                'salida' => InventoryMovementType::CONSUMO_VENTA,
                'merma' => InventoryMovementType::MERMA_DANO,
                'ajuste' => InventoryMovementType::AJUSTE_INVENTARIO,
                'cancelacion' => InventoryMovementType::CANCELACION_PEDIDO,
                InventoryMovementType::COMPRA_ENTRADA => InventoryMovementType::COMPRA_ENTRADA,
                InventoryMovementType::CONSUMO_VENTA => InventoryMovementType::CONSUMO_VENTA,
                InventoryMovementType::MERMA_DANO => InventoryMovementType::MERMA_DANO,
                InventoryMovementType::AJUSTE_INVENTARIO => InventoryMovementType::AJUSTE_INVENTARIO,
                InventoryMovementType::CANCELACION_PEDIDO => InventoryMovementType::CANCELACION_PEDIDO,
            ];

            return $map[$typeInput] ?? null;
        }

        $typeId = $this->input('inventory_movement_type_id');
        if (! empty($typeId)) {
            $movementType = InventoryMovementType::query()->find($typeId);
            return $movementType?->name;
        }

        return null;
    }
}

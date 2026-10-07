<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreatePurchaseOrderRequest',
    title: 'Create Purchase Order Request',
    description: 'Datos para registrar una compra a un proveedor (con o sin solicitud previa)',
    required: ['supplier_id', 'items'],
    example: [
        'supplier_id' => 1,
        'purchase_request_id' => null,
        'expected_date' => '2026-10-15',
        'items' => [
            [
                'supply_id' => 1,
                'ordered_quantity' => 25.0,
                'unit_price' => 35.0,
            ],
        ],
    ],
    properties: [
        new OA\Property(property: 'supplier_id', description: 'ID del proveedor', type: 'integer', example: 1),
        new OA\Property(property: 'purchase_request_id', description: 'ID opcional de solicitud de compra vinculada', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'expected_date', description: 'Fecha prevista de entrega (YYYY-MM-DD)', type: 'string', format: 'date', nullable: true, example: '2026-10-15'),
        new OA\Property(
            property: 'items',
            description: 'Lista de insumos, cantidades y precios',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'supply_id', type: 'integer', example: 1),
                    new OA\Property(property: 'ordered_quantity', type: 'number', example: 25.0),
                    new OA\Property(property: 'unit_price', description: 'Precio unitario pactado (opcional)', type: 'number', nullable: true, example: 35.0),
                ],
                type: 'object'
            )
        ),
    ]
)]
class CreatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'expected_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_id' => ['required', 'integer', 'exists:supplies,id'],
            'items.*.ordered_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Debe seleccionar un proveedor.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
            'purchase_request_id.exists' => 'La solicitud de compra vinculada no existe.',
            'expected_date.date' => 'La fecha de entrega debe ser una fecha válida.',
            'items.required' => 'Debe incluir al menos un insumo en la compra.',
            'items.min' => 'Debe incluir al menos un insumo en la compra.',
            'items.*.supply_id.required' => 'El ID del insumo es obligatorio.',
            'items.*.supply_id.exists' => 'El insumo seleccionado no existe.',
            'items.*.ordered_quantity.required' => 'La cantidad a comprar es obligatoria.',
            'items.*.ordered_quantity.gt' => 'La cantidad debe ser mayor a 0.',
            'items.*.unit_price.min' => 'El precio unitario no puede ser negativo.',
        ];
    }
}

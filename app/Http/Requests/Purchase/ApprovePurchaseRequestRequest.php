<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApprovePurchaseRequestRequest',
    title: 'Approve Purchase Request Request',
    description: 'Datos para aprobar una solicitud de compra y generar la orden de compra al proveedor',
    required: ['supplier_id'],
    example: [
        'supplier_id' => 1,
        'expected_date' => '2026-10-15',
        'items' => [
            [
                'supply_id' => 1,
                'quantity' => 20.0,
                'unit_price' => 35.0,
            ],
        ],
    ],
    properties: [
        new OA\Property(property: 'supplier_id', description: 'ID del proveedor seleccionado', type: 'integer', example: 1),
        new OA\Property(property: 'expected_date', description: 'Fecha prevista de entrega (YYYY-MM-DD)', type: 'string', format: 'date', nullable: true, example: '2026-10-15'),
        new OA\Property(
            property: 'items',
            description: 'Lista opcional de insumos con cantidades aprobadas y precios (si no se especifica, toma los sugeridos)',
            type: 'array',
            nullable: true,
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'supply_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'number', example: 20.0),
                    new OA\Property(property: 'unit_price', description: 'Precio unitario pactado (opcional)', type: 'number', nullable: true, example: 35.0),
                ],
                type: 'object'
            )
        ),
    ]
)]
class ApprovePurchaseRequestRequest extends FormRequest
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
            'expected_date' => ['nullable', 'date'],
            'items' => ['nullable', 'array'],
            'items.*.supply_id' => ['required_with:items', 'integer', 'exists:supplies,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Debe seleccionar un proveedor para generar la orden de compra.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
            'expected_date.date' => 'La fecha de entrega debe ser una fecha válida.',
            'items.*.supply_id.required_with' => 'El ID del insumo es obligatorio.',
            'items.*.supply_id.exists' => 'El insumo seleccionado no existe.',
            'items.*.quantity.required_with' => 'La cantidad a aprobar es obligatoria.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor a 0.',
            'items.*.unit_price.min' => 'El precio unitario no puede ser negativo.',
        ];
    }
}

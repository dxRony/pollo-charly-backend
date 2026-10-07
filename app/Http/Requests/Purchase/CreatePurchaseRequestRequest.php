<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreatePurchaseRequestRequest',
    title: 'Create Purchase Request Request',
    description: 'Datos para registrar una solicitud de compra de insumos',
    required: ['items'],
    example: [
        'reason' => 'Reposición de insumos críticos de cocina',
        'supply_alert_id' => 1,
        'items' => [
            [
                'supply_id' => 1,
                'suggested_quantity' => 20.0,
            ],
        ],
    ],
    properties: [
        new OA\Property(property: 'reason', description: 'Motivo u observaciones de la solicitud', type: 'string', maxLength: 1000, nullable: true, example: 'Reposición de insumos de cocina'),
        new OA\Property(property: 'supply_alert_id', description: 'ID opcional de la alerta que origina la solicitud', type: 'integer', nullable: true, example: 1),
        new OA\Property(
            property: 'items',
            description: 'Lista de insumos a solicitar con su cantidad sugerida',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'supply_id', type: 'integer', example: 1),
                    new OA\Property(property: 'suggested_quantity', type: 'number', example: 20.0),
                ],
                type: 'object'
            )
        ),
    ]
)]
class CreatePurchaseRequestRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:1000'],
            'supply_alert_id' => ['nullable', 'integer', 'exists:supply_alerts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_id' => ['required', 'integer', 'exists:supplies,id'],
            'items.*.suggested_quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'El motivo no puede exceder los 1000 caracteres.',
            'supply_alert_id.exists' => 'La alerta de reposición especificada no existe.',
            'items.required' => 'Debe incluir al menos un insumo en la solicitud.',
            'items.min' => 'Debe incluir al menos un insumo en la solicitud.',
            'items.*.supply_id.required' => 'El ID del insumo es obligatorio.',
            'items.*.supply_id.exists' => 'El insumo seleccionado no existe.',
            'items.*.suggested_quantity.required' => 'La cantidad sugerida es obligatoria.',
            'items.*.suggested_quantity.numeric' => 'La cantidad sugerida debe ser un número.',
            'items.*.suggested_quantity.gt' => 'La cantidad sugerida debe ser mayor a 0.',
        ];
    }
}

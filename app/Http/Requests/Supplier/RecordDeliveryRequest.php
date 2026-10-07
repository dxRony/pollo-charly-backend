<?php

declare(strict_types=1);

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RecordDeliveryRequest',
    title: 'Record Delivery Request',
    description: 'Datos para registrar la recepción de una entrega de proveedor, con o sin incidencias',
    required: ['has_incident'],
    example: [
        'has_incident' => true,
        'purchase_order_id' => 1,
        'delivery_incident_type_id' => 1,
        'description' => 'El pedido llegó incompleto: faltaron 5 kg de pechuga de pollo.',
        'evidence_path' => null,
        'notes' => 'Conductor de la entrega firmó acta de diferencia.',
    ],
    properties: [
        new OA\Property(property: 'has_incident', description: 'Indica si se detectó alguna anomalía/incidencia durante la recepción', type: 'boolean', example: true),
        new OA\Property(property: 'purchase_order_id', description: 'ID de la orden de compra que se recibe (opcional)', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'delivery_incident_type_id', description: 'ID del tipo de incidencia (obligatorio si has_incident es verdadero)', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'description', description: 'Descripción detallada de la incidencia (obligatorio si has_incident es verdadero)', type: 'string', maxLength: 1000, nullable: true, example: 'El pedido llegó incompleto: faltaron 5 kg.'),
        new OA\Property(property: 'evidence_path', description: 'Ruta o URL de comprobante/foto de la incidencia', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'notes', description: 'Notas u observaciones adicionales de la entrega', type: 'string', maxLength: 500, nullable: true, example: 'Entrega recibida en turno matutino.'),
        new OA\Property(
            property: 'items',
            description: 'Detalle de insumos recibidos con sus cantidades',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                required: ['supply_id', 'received_quantity'],
                properties: [
                    new OA\Property(property: 'supply_id', type: 'integer', example: 1),
                    new OA\Property(property: 'received_quantity', type: 'number', example: 15.00),
                    new OA\Property(property: 'unit_price', type: 'number', nullable: true, example: 32.50),
                ]
            )
        ),
    ]
)]
class RecordDeliveryRequest extends FormRequest
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
            'has_incident' => ['required', 'boolean'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'delivery_incident_type_id' => [
                'required_if:has_incident,true',
                'nullable',
                'integer',
                'exists:delivery_incident_types,id',
            ],
            'description' => [
                'required_if:has_incident,true',
                'nullable',
                'string',
                'min:5',
                'max:1000',
            ],
            'evidence_path' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['sometimes', 'array'],
            'items.*.supply_id' => ['required_with:items', 'integer', 'exists:supplies,id'],
            'items.*.received_quantity' => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Mensajes personalizados de error en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'has_incident.required' => 'Debe indicar si la entrega presenta alguna incidencia o no.',
            'has_incident.boolean' => 'El indicador de incidencia debe ser verdadero o falso.',
            'purchase_order_id.exists' => 'La orden de compra seleccionada no es válida.',
            'delivery_incident_type_id.required_if' => 'Debe seleccionar el tipo de incidencia cuando se reporta un problema con la entrega.',
            'delivery_incident_type_id.exists' => 'El tipo de incidencia seleccionado no es válido.',
            'description.required_if' => 'Debe proporcionar una descripción detallada de la incidencia identificada.',
            'description.min' => 'La descripción de la incidencia debe contener al menos 5 caracteres.',
            'description.max' => 'La descripción de la incidencia no puede superar los 1000 caracteres.',
            'items.array' => 'Los insumos recibidos deben ser un listado.',
            'items.*.supply_id.required_with' => 'El identificador del insumo es obligatorio en la lista de items.',
            'items.*.supply_id.exists' => 'Uno de los insumos especificados no existe en el catálogo.',
            'items.*.received_quantity.required_with' => 'La cantidad recibida es obligatoria para cada insumo.',
            'items.*.received_quantity.min' => 'La cantidad recibida debe ser mayor a 0.',
        ];
    }
}

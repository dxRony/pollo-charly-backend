<?php

declare(strict_types=1);

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateDeliveryIncidentRequest',
    title: 'Create Delivery Incident Request',
    description: 'Datos para reportar una incidencia de entrega directamente',
    required: ['supplier_id', 'delivery_incident_type_id', 'description'],
    example: [
        'supplier_id' => 1,
        'purchase_order_id' => 2,
        'delivery_incident_type_id' => 1,
        'description' => 'El proveedor entregó 10 kg en lugar de los 15 kg solicitados.',
        'evidence_path' => null,
    ],
    properties: [
        new OA\Property(property: 'supplier_id', description: 'ID del proveedor al que corresponde la entrega', type: 'integer', example: 1),
        new OA\Property(property: 'purchase_order_id', description: 'ID de la orden de compra si existe', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'delivery_incident_type_id', description: 'ID del tipo de anomalía detectada', type: 'integer', example: 1),
        new OA\Property(property: 'description', description: 'Explicación detallada del problema de peso, calidad o retraso', type: 'string', minLength: 5, maxLength: 1000, example: 'El proveedor entregó 10 kg en lugar de los 15 kg solicitados.'),
        new OA\Property(property: 'evidence_path', description: 'Ruta o URL del comprobante de incidencia', type: 'string', maxLength: 255, nullable: true, example: null),
    ]
)]
class CreateDeliveryIncidentRequest extends FormRequest
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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'delivery_incident_type_id' => ['required', 'integer', 'exists:delivery_incident_types,id'],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
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
            'supplier_id.required' => 'El proveedor es obligatorio.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe.',
            'purchase_order_id.exists' => 'La orden de compra seleccionada no es válida.',
            'delivery_incident_type_id.required' => 'Debe seleccionar el tipo de incidencia.',
            'delivery_incident_type_id.exists' => 'El tipo de incidencia seleccionado no es válido.',
            'description.required' => 'La descripción de la incidencia es obligatoria.',
            'description.min' => 'La descripción debe tener al menos 5 caracteres.',
            'description.max' => 'La descripción no puede superar los 1000 caracteres.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReportOrderIncidentRequest',
    title: 'Report Order Incident Request',
    description: 'Datos para reportar una incidencia durante la recepción de una compra a proveedor',
    required: ['delivery_incident_type_id', 'description'],
    example: [
        'delivery_incident_type_id' => 1,
        'description' => 'Faltan 5 kg de pechuga de pollo respecto a la cantidad solicitada.',
        'evidence_path' => null,
    ],
    properties: [
        new OA\Property(property: 'delivery_incident_type_id', description: 'ID del tipo de incidencia (peso_incompleto, producto_danado, producto_equivocado, retraso, otro)', type: 'integer', example: 1),
        new OA\Property(property: 'description', description: 'Detalle o descripción de la no conformidad detectada', type: 'string', maxLength: 1000, example: 'Diferencia de peso encontrada en balanza.'),
        new OA\Property(property: 'evidence_path', description: 'Ruta o URL opcional de evidencia fotográfica', type: 'string', maxLength: 2048, nullable: true, example: null),
    ]
)]
class ReportOrderIncidentRequest extends FormRequest
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
            'delivery_incident_type_id' => ['required', 'integer', 'exists:delivery_incident_types,id'],
            'description' => ['required', 'string', 'max:1000'],
            'evidence_path' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_incident_type_id.required' => 'Debe seleccionar el tipo de incidencia.',
            'delivery_incident_type_id.exists' => 'El tipo de incidencia seleccionado no es válido.',
            'description.required' => 'Debe detallar el motivo o descripción de la incidencia.',
            'description.max' => 'La descripción no puede exceder los 1000 caracteres.',
        ];
    }
}

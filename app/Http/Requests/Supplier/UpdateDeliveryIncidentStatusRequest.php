<?php

declare(strict_types=1);

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateDeliveryIncidentStatusRequest',
    title: 'Update Delivery Incident Status Request',
    description: 'Datos para cambiar el estado de seguimiento de una incidencia de entrega',
    required: ['delivery_incident_status_id'],
    example: [
        'delivery_incident_status_id' => 3,
        'resolution_notes' => 'El proveedor repuso los 5 kg faltantes en la siguiente entrega.',
    ],
    properties: [
        new OA\Property(property: 'delivery_incident_status_id', description: 'ID del nuevo estado de la incidencia (1=reportada, 2=en_correccion, 3=resuelta)', type: 'integer', example: 3),
        new OA\Property(property: 'resolution_notes', description: 'Notas de resolución o seguimiento', type: 'string', maxLength: 500, nullable: true, example: 'El proveedor repuso los faltantes.'),
    ]
)]
class UpdateDeliveryIncidentStatusRequest extends FormRequest
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
            'delivery_incident_status_id' => ['required', 'integer', 'exists:delivery_incident_statuses,id'],
            'resolution_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_incident_status_id.required' => 'El nuevo estado de la incidencia es obligatorio.',
            'delivery_incident_status_id.exists' => 'El estado de incidencia seleccionado no es válido.',
            'resolution_notes.max' => 'Las notas de resolución no pueden superar los 500 caracteres.',
        ];
    }
}

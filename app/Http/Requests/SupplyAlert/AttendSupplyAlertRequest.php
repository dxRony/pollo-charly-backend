<?php

declare(strict_types=1);

namespace App\Http\Requests\SupplyAlert;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AttendSupplyAlertRequest',
    title: 'Attend Supply Alert Request',
    description: 'Datos opcionales para marcar como atendida una alerta de reposición',
    example: [
        'purchase_request_id' => 1,
        'notes' => 'Se generó la solicitud de compra SC-0001 para reposición inmediata.',
    ],
    properties: [
        new OA\Property(property: 'purchase_request_id', description: 'ID de la solicitud de compra vinculada (opcional)', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'notes', description: 'Notas u observaciones al atender la alerta', type: 'string', maxLength: 1000, nullable: true, example: 'Insumo incluido en solicitud de abastecimiento'),
    ]
)]
class AttendSupplyAlertRequest extends FormRequest
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
            'purchase_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Mensajes personalizados en español para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_request_id.exists' => 'La solicitud de compra especificada no existe.',
            'notes.string' => 'Las notas deben ser una cadena de texto.',
            'notes.max' => 'Las notas no pueden exceder los 1000 caracteres.',
        ];
    }
}

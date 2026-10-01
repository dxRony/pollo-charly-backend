<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReviewAdjustmentRequest',
    title: 'Review Adjustment Request',
    description: 'Datos opcionales para la aprobación o rechazo de una solicitud de ajuste de inventario',
    example: [
        'reason' => 'Ajuste revisado y verificado con conteo físico en almacén.',
    ],
    properties: [
        new OA\Property(property: 'reason', description: 'Observación o motivo de la revisión (aprobación o rechazo)', type: 'string', maxLength: 1000, nullable: true, example: 'Aprobado tras verificar merma física'),
        new OA\Property(property: 'notes', description: 'Notas adicionales de la administradora', type: 'string', maxLength: 1000, nullable: true, example: 'Se verificó con el cocinero en turno'),
    ]
)]
class ReviewAdjustmentRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'reason.max' => 'El motivo u observación no puede exceder los 1000 caracteres.',
            'notes.max' => 'Las notas no pueden exceder los 1000 caracteres.',
        ];
    }
}

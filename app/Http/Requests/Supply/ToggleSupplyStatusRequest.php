<?php

declare(strict_types=1);

namespace App\Http\Requests\Supply;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ToggleSupplyStatusRequest',
    title: 'Toggle Supply Status Request',
    description: 'Datos requeridos para activar o desactivar la disponibilidad de un insumo',
    required: ['is_active'],
    example: [
        'is_active' => false,
    ],
    properties: [
        new OA\Property(property: 'is_active', description: 'Nuevo estado de activación del insumo', type: 'boolean', example: false),
    ]
)]
class ToggleSupplyStatusRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el cambio de estado.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Mensajes personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.required' => 'El estado de activación es obligatorio.',
            'is_active.boolean' => 'El estado de activación debe ser verdadero o falso.',
        ];
    }
}

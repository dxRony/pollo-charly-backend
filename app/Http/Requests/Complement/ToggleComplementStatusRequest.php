<?php

declare(strict_types=1);

namespace App\Http\Requests\Complement;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ToggleComplementStatusRequest',
    title: 'Toggle Complement Status Request',
    description: 'Datos requeridos para activar o desactivar un complemento en el menú',
    required: ['is_active'],
    example: [
        'is_active' => false,
    ],
    properties: [
        new OA\Property(property: 'is_active', description: 'Nuevo estado de activación del complemento', type: 'boolean', example: false),
    ]
)]
class ToggleComplementStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
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

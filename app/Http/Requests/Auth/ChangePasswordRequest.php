<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ChangePasswordRequest',
    title: 'Change Password Request',
    description: 'Datos necesarios para que un usuario autenticado cambie su propia contraseña',
    required: ['current_password', 'password', 'password_confirmation'],
    example: [
        'current_password' => 'passwordActual123',
        'password' => 'nuevaPassword123',
        'password_confirmation' => 'nuevaPassword123',
    ],
    properties: [
        new OA\Property(
            property: 'current_password',
            description: 'Contraseña actual del usuario',
            type: 'string',
            format: 'password',
            example: 'passwordActual123'
        ),
        new OA\Property(
            property: 'password',
            description: 'Nueva contraseña (mínimo 8 caracteres y distinta de la actual)',
            type: 'string',
            format: 'password',
            example: 'nuevaPassword123'
        ),
        new OA\Property(
            property: 'password_confirmation',
            description: 'Confirmación de la nueva contraseña',
            type: 'string',
            format: 'password',
            example: 'nuevaPassword123'
        ),
    ]
)]
class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'La contraseña actual es obligatoria.',
            'current_password.current_password' => 'La contraseña actual es incorrecta.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ];
    }
}

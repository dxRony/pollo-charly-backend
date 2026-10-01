<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ResetPasswordRequest',
    title: 'Reset Password Request',
    description: 'Datos necesarios para restablecer la contraseña',
    required: ['token', 'email', 'password', 'password_confirmation'],
    example: [
        'token' => '60e1d8820f4c9c1b33fa4d173d12a...',
        'email' => 'admin@pollocharly.com',
        'password' => 'nuevaPassword123',
        'password_confirmation' => 'nuevaPassword123',
    ],
    properties: [
        new OA\Property(
            property: 'token',
            description: 'Token de recuperación recibido en el correo',
            type: 'string',
            example: '60e1d8820f4c9c1b33fa4d173d12a...'
        ),
        new OA\Property(
            property: 'email',
            description: 'Correo electrónico de la cuenta',
            type: 'string',
            format: 'email',
            example: 'admin@pollocharly.com'
        ),
        new OA\Property(
            property: 'password',
            description: 'Nueva contraseña (mínimo 8 caracteres)',
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
class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El token de recuperación es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'password.required' => 'La nueva contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}

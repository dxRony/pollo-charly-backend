<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'VerifyTwoFactorRequest',
    title: 'Verify Two-Factor Authentication Request',
    description: 'Datos requeridos para validar el código temporal de verificación de dos factores (2FA)',
    required: ['email', 'code'],
    example: [
        'email' => 'admin@pollocharly.com',
        'code' => '123456',
    ],
    properties: [
        new OA\Property(
            property: 'email',
            description: 'Correo electrónico del usuario que inició sesión',
            type: 'string',
            format: 'email',
            example: 'admin@pollocharly.com'
        ),
        new OA\Property(
            property: 'code',
            description: 'Código de verificación de 6 dígitos numéricos enviado al correo',
            type: 'string',
            maxLength: 6,
            minLength: 6,
            example: '123456'
        ),
    ]
)]
class VerifyTwoFactorRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'code.required' => 'El código de verificación es obligatorio.',
            'code.string' => 'El código de verificación debe ser una cadena de texto.',
            'code.size' => 'El código de verificación debe tener exactamente 6 dígitos.',
        ];
    }
}

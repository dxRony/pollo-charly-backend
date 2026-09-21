<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ForgotPasswordRequest',
    title: 'Forgot Password Request',
    description: 'Correo electrónico para solicitar el enlace o token de recuperación',
    required: ['email'],
    example: [
        'email' => 'admin@pollocharly.com',
    ],
    properties: [
        new OA\Property(
            property: 'email',
            description: 'Correo electrónico registrado del usuario',
            type: 'string',
            format: 'email',
            example: 'admin@pollocharly.com'
        ),
    ]
)]
class ForgotPasswordRequest extends FormRequest
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
        ];
    }
}

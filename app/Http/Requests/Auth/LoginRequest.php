<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LoginRequest',
    title: 'Login Request',
    description: 'Credenciales requeridas para autenticación',
    required: ['email', 'password'],
    example: [
        'email' => 'admin@pollocharly.com',
        'password' => 'password',
    ],
    properties: [
        new OA\Property(
            property: 'email',
            description: 'Correo electrónico del usuario',
            type: 'string',
            format: 'email',
            example: 'admin@pollocharly.com'
        ),
        new OA\Property(
            property: 'password',
            description: 'Contraseña del usuario',
            type: 'string',
            format: 'password',
            example: 'password'
        ),
    ]
)]
class LoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }
}

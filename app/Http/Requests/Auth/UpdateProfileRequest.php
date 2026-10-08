<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateProfileRequest',
    title: 'Update Profile Request',
    description: 'Datos permitidos para modificar el perfil del usuario autenticado',
    required: ['name', 'email'],
    example: [
        'name' => 'Carlos Alberto Mesero',
        'email' => 'carlos.mesero@pollocharly.com',
    ],
    properties: [
        new OA\Property(property: 'name', description: 'Nombre completo del usuario', type: 'string', example: 'Carlos Alberto Mesero'),
        new OA\Property(property: 'email', description: 'Correo electrónico del usuario', type: 'string', format: 'email', example: 'carlos.mesero@pollocharly.com'),
    ]
)]
class UpdateProfileRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.max' => 'El correo electrónico no puede exceder los 255 caracteres.',
            'email.unique' => 'El correo electrónico ya está registrado por otro usuario.',
        ];
    }
}

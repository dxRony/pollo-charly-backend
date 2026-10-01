<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateUserRequest',
    title: 'Create User Request',
    description: 'Datos necesarios para registrar un nuevo usuario en el sistema',
    required: ['name', 'email', 'password', 'role_id'],
    example: [
        'name' => 'Carlos Mesero',
        'email' => 'nuevo.mesero@pollocharly.com',
        'password' => 'temporal123',
        'role_id' => 2,
        'is_active' => true,
    ],
    properties: [
        new OA\Property(property: 'name', description: 'Nombre completo del trabajador', type: 'string', example: 'Carlos Mesero'),
        new OA\Property(property: 'email', description: 'Correo electrónico único corporativo o personal', type: 'string', format: 'email', example: 'nuevo.mesero@pollocharly.com'),
        new OA\Property(property: 'password', description: 'Contraseña inicial de acceso (mínimo 8 caracteres)', type: 'string', format: 'password', example: 'temporal123'),
        new OA\Property(property: 'role_id', description: 'Identificador del rol a asignar (1: Administrador, 2: Mesero/Cajero, 3: Cocinero)', type: 'integer', example: 2),
        new OA\Property(property: 'is_active', description: 'Estado inicial de la cuenta (por defecto true)', type: 'boolean', example: true),
    ]
)]
class CreateUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del usuario es obligatorio.',
            'name.string' => 'El nombre debe ser una cadena de texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'email.unique' => 'El correo electrónico ya está registrado en el sistema.',
            'email.max' => 'El correo electrónico no puede exceder los 255 caracteres.',
            'password.required' => 'La contraseña inicial es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'role_id.required' => 'El rol asignado es obligatorio.',
            'role_id.integer' => 'El identificador del rol debe ser un número entero.',
            'role_id.exists' => 'El rol seleccionado no es válido.',
            'is_active.boolean' => 'El estado de activación debe ser verdadero o falso.',
        ];
    }
}

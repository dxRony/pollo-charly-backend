<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateUserRequest',
    title: 'Update User Request',
    description: 'Datos permitidos para modificar un usuario existente',
    required: ['name', 'email', 'role_id'],
    example: [
        'name' => 'Carlos Alberto Mesero',
        'email' => 'carlos.mesero@pollocharly.com',
        'password' => 'nuevaPassword123',
        'role_id' => 2,
        'is_active' => true,
    ],
    properties: [
        new OA\Property(property: 'name', description: 'Nombre completo del trabajador', type: 'string', example: 'Carlos Alberto Mesero'),
        new OA\Property(property: 'email', description: 'Correo electrónico único corporativo o personal', type: 'string', format: 'email', example: 'carlos.mesero@pollocharly.com'),
        new OA\Property(property: 'password', description: 'Nueva contraseña (opcional, mínimo 8 caracteres)', type: 'string', format: 'password', nullable: true, example: 'nuevaPassword123'),
        new OA\Property(property: 'role_id', description: 'Identificador del rol a asignar', type: 'integer', example: 2),
        new OA\Property(property: 'is_active', description: 'Estado de la cuenta', type: 'boolean', example: true),
    ]
)]
class UpdateUserRequest extends FormRequest
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
        $userParam = $this->route('user');
        $userId = $userParam instanceof User ? $userParam->id : $userParam;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8'],
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
            'email.unique' => 'El correo electrónico ya está registrado por otro usuario.',
            'email.max' => 'El correo electrónico no puede exceder los 255 caracteres.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres si se desea modificar.',
            'role_id.required' => 'El rol asignado es obligatorio.',
            'role_id.integer' => 'El identificador del rol debe ser un número entero.',
            'role_id.exists' => 'El rol seleccionado no es válido.',
            'is_active.boolean' => 'El estado de activación debe ser verdadero o falso.',
        ];
    }
}

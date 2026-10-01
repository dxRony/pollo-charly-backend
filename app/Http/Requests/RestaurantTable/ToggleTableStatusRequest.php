<?php

declare(strict_types=1);

namespace App\Http\Requests\RestaurantTable;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ToggleTableStatusRequest',
    title: 'Toggle Table Status Request',
    description: 'Parámetros para cambiar el estado operativo o dar de baja una mesa',
    properties: [
        new OA\Property(property: 'status', description: 'Nombre del nuevo estado (disponible, mantenimiento, inactiva)', type: 'string', enum: ['disponible', 'mantenimiento', 'inactiva'], nullable: true, example: 'inactiva'),
        new OA\Property(property: 'is_active', description: 'Atajo booleano (false para inactivar, true para poner disponible)', type: 'boolean', nullable: true, example: false),
        new OA\Property(property: 'table_status_id', description: 'ID del estado en la base de datos', type: 'integer', nullable: true, example: 4),
    ]
)]
class ToggleTableStatusRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene permisos para cambiar el estado de las mesas.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::ADMINISTRADOR) ?? false;
    }

    /**
     * Respuesta personalizada en español cuando el usuario no tiene permisos.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para gestionar mesas. Se requiere rol de Administrador.',
        ], 403));
    }

    /**
     * Reglas de validación aplicadas al cambio de estado de una mesa.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:disponible,mantenimiento,inactiva'],
            'is_active' => ['nullable', 'boolean'],
            'table_status_id' => ['nullable', 'integer', 'exists:table_statuses,id'],
        ];
    }

    /**
     * Mensajes de error en español para las validaciones.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'El estado indicado debe ser disponible, mantenimiento o inactiva.',
            'is_active.boolean' => 'El campo is_active debe ser verdadero o falso.',
            'table_status_id.exists' => 'El estado de mesa seleccionado no es válido.',
        ];
    }
}

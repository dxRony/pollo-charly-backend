<?php

declare(strict_types=1);

namespace App\Http\Requests\RestaurantTable;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateTableRequest',
    title: 'Update Table Request',
    description: 'Datos permitidos para actualizar una mesa existente',
    properties: [
        new OA\Property(property: 'number', description: 'Número identificador único de la mesa en el restaurante', type: 'integer', example: 5, nullable: true),
        new OA\Property(property: 'capacity', description: 'Capacidad máxima de comensales sentados', type: 'integer', example: 6, nullable: true),
        new OA\Property(property: 'table_status_id', description: 'ID de estado de la mesa', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'status', description: 'Nombre del estado (disponible, mantenimiento, inactiva)', type: 'string', enum: ['disponible', 'mantenimiento', 'inactiva'], nullable: true, example: 'disponible'),
    ]
)]
class UpdateTableRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene permisos para editar mesas.
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
     * Reglas de validación aplicadas a la actualización de una mesa.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tableId = $this->route('restaurant_table')?->id
            ?? $this->route('id')
            ?? $this->route('table');

        return [
            'number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('restaurant_tables', 'number')->ignore($tableId),
            ],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:50'],
            'table_status_id' => ['nullable', 'integer', 'exists:table_statuses,id'],
            'status' => ['nullable', 'string', 'in:disponible,ocupada,mantenimiento,inactiva'],
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
            'number.required' => 'El número de mesa es obligatorio.',
            'number.integer' => 'El número de mesa debe ser un valor entero.',
            'number.min' => 'El número de mesa debe ser un entero positivo mayor o igual a 1.',
            'number.unique' => 'El número de mesa ya se encuentra registrado.',
            'capacity.required' => 'La capacidad de comensales es obligatoria.',
            'capacity.integer' => 'La capacidad debe ser un número entero.',
            'capacity.min' => 'La capacidad mínima de la mesa debe ser de al menos 1 comensal.',
            'capacity.max' => 'La capacidad máxima permitida por mesa es de 50 comensales.',
            'table_status_id.exists' => 'El estado de mesa seleccionado no es válido.',
            'status.in' => 'El estado indicado debe ser disponible, ocupada, mantenimiento o inactiva.',
        ];
    }
}

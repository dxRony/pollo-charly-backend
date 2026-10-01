<?php

declare(strict_types=1);

namespace App\Http\Requests\RestaurantTable;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateTableRequest',
    title: 'Create Table Request',
    description: 'Datos necesarios para registrar una nueva mesa en el salón del restaurante',
    required: ['number', 'capacity'],
    properties: [
        new OA\Property(property: 'number', description: 'Número identificador único de la mesa en el restaurante', type: 'integer', example: 5),
        new OA\Property(property: 'capacity', description: 'Capacidad máxima de comensales sentados', type: 'integer', example: 4),
        new OA\Property(property: 'table_status_id', description: 'ID de estado inicial opcional (por defecto 1 = disponible)', type: 'integer', nullable: true, example: 1),
    ]
)]
class CreateTableRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene permisos para registrar mesas.
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
     * Reglas de validación aplicadas a la creación de una mesa.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1', 'unique:restaurant_tables,number'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
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
            'number.required' => 'El número de mesa es obligatorio.',
            'number.integer' => 'El número de mesa debe ser un valor entero.',
            'number.min' => 'El número de mesa debe ser un entero positivo mayor o igual a 1.',
            'number.unique' => 'El número de mesa ya se encuentra registrado.',
            'capacity.required' => 'La capacidad de comensales es obligatoria.',
            'capacity.integer' => 'La capacidad debe ser un número entero.',
            'capacity.min' => 'La capacidad mínima de la mesa debe ser de al menos 1 comensal.',
            'capacity.max' => 'La capacidad máxima permitida por mesa es de 50 comensales.',
            'table_status_id.exists' => 'El estado de mesa seleccionado no es válido.',
        ];
    }
}

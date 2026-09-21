<?php

declare(strict_types=1);

namespace App\Http\Requests\Supply;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateSupplyRequest',
    title: 'Create Supply Request',
    description: 'Datos necesarios para dar de alta un producto o insumo en el inventario',
    required: ['name', 'measurement_unit_id', 'minimum_stock', 'unit_cost'],
    example: [
        'code' => 'INS-0001',
        'name' => 'Pechuga de Pollo Fresca',
        'measurement_unit_id' => 1,
        'minimum_stock' => 15.00,
        'unit_cost' => 35.00,
        'is_active' => true,
    ],
    properties: [
        new OA\Property(property: 'code', description: 'Código único opcional (se genera automáticamente si se omite)', type: 'string', maxLength: 50, nullable: true, example: 'INS-0001'),
        new OA\Property(property: 'name', description: 'Nombre descriptivo del producto o insumo', type: 'string', maxLength: 150, example: 'Pechuga de Pollo Fresca'),
        new OA\Property(property: 'measurement_unit_id', description: 'ID de la unidad de medida', type: 'integer', example: 1),
        new OA\Property(property: 'minimum_stock', description: 'Cantidad de referencia mínima requerida en almacén (mayor a 0)', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'unit_cost', description: 'Costo unitario de adquisición (mayor o igual a 0)', type: 'number', format: 'float', example: 35.00),
        new OA\Property(property: 'is_active', description: 'Estado inicial del insumo', type: 'boolean', example: true),
    ]
)]
class CreateSupplyRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación aplicadas a la solicitud.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'unique:supplies,code'],
            'measurement_unit_id' => ['required', 'integer', 'exists:measurement_units,id'],
            'minimum_stock' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Mensajes personalizados en español para las reglas de validación.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del insumo es obligatorio.',
            'name.string' => 'El nombre del insumo debe ser una cadena de texto.',
            'name.max' => 'El nombre del insumo no puede exceder los 150 caracteres.',
            'code.unique' => 'El código de insumo ingresado ya está registrado por otro producto.',
            'code.max' => 'El código no puede exceder los 50 caracteres.',
            'measurement_unit_id.required' => 'La unidad de medida es obligatoria.',
            'measurement_unit_id.integer' => 'El identificador de la unidad de medida debe ser un número entero.',
            'measurement_unit_id.exists' => 'La unidad de medida seleccionada no es válida.',
            'minimum_stock.required' => 'La cantidad de referencia (stock mínimo) es obligatoria.',
            'minimum_stock.numeric' => 'La cantidad de referencia debe ser un valor numérico.',
            'minimum_stock.min' => 'La cantidad de referencia debe ser un valor positivo mayor a 0.',
            'unit_cost.required' => 'El costo unitario es obligatorio.',
            'unit_cost.numeric' => 'El costo unitario debe ser un valor numérico.',
            'unit_cost.min' => 'El costo unitario debe ser mayor o igual a 0.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}

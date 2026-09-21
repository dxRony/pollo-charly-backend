<?php

declare(strict_types=1);

namespace App\Http\Requests\Complement;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateComplementSupplyItemInput',
    title: 'Create Complement Supply Item Input',
    required: ['supply_id', 'required_quantity'],
    properties: [
        new OA\Property(property: 'supply_id', description: 'ID del insumo', type: 'integer', example: 1),
        new OA\Property(property: 'required_quantity', description: 'Cantidad requerida de insumo por porción (mayor a 0)', type: 'number', format: 'float', example: 0.1),
    ]
)]
#[OA\Schema(
    schema: 'CreateComplementRequest',
    title: 'Create Complement Request',
    description: 'Datos requeridos para registrar un nuevo complemento en el sistema',
    required: ['name', 'extra_price'],
    example: [
        'name' => 'Extra queso',
        'description' => 'Porción extra de queso fundido para acompañar',
        'extra_price' => 10.00,
        'is_active' => true,
        'supplies' => [
            ['supply_id' => 1, 'required_quantity' => 0.1],
        ],
    ],
    properties: [
        new OA\Property(property: 'name', description: 'Nombre único del complemento', type: 'string', maxLength: 150, example: 'Extra queso'),
        new OA\Property(property: 'description', description: 'Descripción opcional del complemento', type: 'string', nullable: true, example: 'Porción extra de queso fundido para acompañar'),
        new OA\Property(property: 'extra_price', description: 'Precio adicional a sumar al platillo (mayor o igual a 0)', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'is_active', description: 'Estado inicial de disponibilidad', type: 'boolean', example: true),
        new OA\Property(
            property: 'supplies',
            description: 'Insumos que se descuentan del inventario al servir este complemento',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CreateComplementSupplyItemInput')
        ),
    ]
)]
class CreateComplementRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150', 'unique:complements,name'],
            'description' => ['nullable', 'string'],
            'extra_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'supplies' => ['nullable', 'array'],
            'supplies.*.supply_id' => ['required_with:supplies', 'integer', 'exists:supplies,id'],
            'supplies.*.required_quantity' => ['required_with:supplies', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del complemento es obligatorio.',
            'name.unique' => 'El nombre del complemento ya existe.',
            'name.max' => 'El nombre del complemento no puede exceder 150 caracteres.',
            'extra_price.required' => 'El precio adicional es obligatorio.',
            'extra_price.numeric' => 'El precio adicional debe ser un valor numérico.',
            'extra_price.min' => 'El precio adicional debe ser mayor o igual a 0.',
            'supplies.*.supply_id.required_with' => 'El insumo asociado es obligatorio.',
            'supplies.*.supply_id.exists' => 'El insumo seleccionado no existe.',
            'supplies.*.required_quantity.required_with' => 'La cantidad requerida del insumo es obligatoria.',
            'supplies.*.required_quantity.numeric' => 'La cantidad requerida debe ser numérica.',
            'supplies.*.required_quantity.gt' => 'La cantidad requerida debe ser mayor a 0.',
        ];
    }
}

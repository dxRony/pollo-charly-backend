<?php

declare(strict_types=1);

namespace App\Http\Requests\Complement;

use App\Models\Complement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateComplementRequest',
    title: 'Update Complement Request',
    description: 'Datos permitidos para modificar un complemento existente',
    required: ['name', 'extra_price'],
    example: [
        'name' => 'Extra queso fundido',
        'description' => 'Porción generosa de queso fundido',
        'extra_price' => 12.00,
        'is_active' => true,
        'supplies' => [
            ['supply_id' => 1, 'required_quantity' => 0.12],
        ],
    ],
    properties: [
        new OA\Property(property: 'name', description: 'Nombre único del complemento', type: 'string', maxLength: 150, example: 'Extra queso fundido'),
        new OA\Property(property: 'description', description: 'Descripción opcional del complemento', type: 'string', nullable: true, example: 'Porción generosa de queso fundido'),
        new OA\Property(property: 'extra_price', description: 'Precio adicional a sumar al platillo (mayor o igual a 0)', type: 'number', format: 'float', example: 12.00),
        new OA\Property(property: 'is_active', description: 'Estado del complemento', type: 'boolean', example: true),
        new OA\Property(
            property: 'supplies',
            description: 'Lista de insumos asociados (reemplaza la lista previa si se provee)',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CreateComplementSupplyItemInput')
        ),
    ]
)]
class UpdateComplementRequest extends FormRequest
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
        $complementParam = $this->route('complement');
        $complementId = $complementParam instanceof Complement ? $complementParam->id : $complementParam;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('complements', 'name')->ignore($complementId),
            ],
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

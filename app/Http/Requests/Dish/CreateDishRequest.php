<?php

declare(strict_types=1);

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateDishRecipeItemInput',
    title: 'Create Dish Recipe Item Input',
    required: ['supply_id', 'required_quantity'],
    properties: [
        new OA\Property(property: 'supply_id', description: 'ID del insumo', type: 'integer', example: 1),
        new OA\Property(property: 'required_quantity', description: 'Cantidad requerida por porción (mayor a 0)', type: 'number', format: 'float', example: 0.5),
    ]
)]
#[OA\Schema(
    schema: 'CreateDishRequest',
    title: 'Create Dish Request',
    description: 'Datos para registrar un nuevo platillo junto con su receta y complementos asociados',
    required: ['name', 'category_id', 'price'],
    example: [
        'name' => 'Pollo Frito Familiar',
        'category_id' => 1,
        'description' => '8 piezas crujientes de pollo acompañadas de papas',
        'price' => 125.00,
        'image_url' => 'https://cdn.pollocharly.com/dishes/familiar.jpg',
        'is_daily_menu' => false,
        'is_active' => true,
        'recipes' => [
            ['supply_id' => 1, 'required_quantity' => 1.5],
            ['supply_id' => 2, 'required_quantity' => 0.25],
        ],
        'complements' => [1, 2],
    ],
    properties: [
        new OA\Property(property: 'name', description: 'Nombre único del platillo', type: 'string', maxLength: 150, example: 'Pollo Frito Familiar'),
        new OA\Property(property: 'category_id', description: 'ID de la categoría a la que pertenece', type: 'integer', example: 1),
        new OA\Property(property: 'description', description: 'Descripción detallada del platillo', type: 'string', nullable: true, example: '8 piezas crujientes de pollo'),
        new OA\Property(property: 'price', description: 'Precio de venta (mayor a 0)', type: 'number', format: 'float', example: 125.00),
        new OA\Property(property: 'image_url', description: 'URL de la imagen del platillo', type: 'string', nullable: true, example: 'https://cdn.pollocharly.com/dishes/familiar.jpg'),
        new OA\Property(property: 'is_daily_menu', description: 'Indica si forma parte del menú del día', type: 'boolean', example: false),
        new OA\Property(property: 'is_active', description: 'Estado inicial del platillo', type: 'boolean', example: true),
        new OA\Property(
            property: 'recipes',
            description: 'Lista de insumos y cantidades requeridas por porción',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CreateDishRecipeItemInput')
        ),
        new OA\Property(
            property: 'complements',
            description: 'IDs de complementos aplicables al platillo',
            type: 'array',
            items: new OA\Items(type: 'integer', example: 1)
        ),
    ]
)]
class CreateDishRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150', 'unique:dishes,name'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'is_daily_menu' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'recipes' => ['nullable', 'array'],
            'recipes.*.supply_id' => ['required_with:recipes', 'integer', 'exists:supplies,id'],
            'recipes.*.required_quantity' => ['required_with:recipes', 'numeric', 'gt:0'],
            'complements' => ['nullable', 'array'],
            'complements.*' => ['integer', 'exists:complements,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del platillo es obligatorio.',
            'name.unique' => 'El nombre del platillo ya existe.',
            'name.max' => 'El nombre del platillo no puede exceder 150 caracteres.',
            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',
            'price.required' => 'El precio de venta es obligatorio.',
            'price.numeric' => 'El precio debe ser un valor numérico.',
            'price.min' => 'El precio de venta debe ser mayor a 0.',
            'recipes.*.supply_id.required_with' => 'El insumo de la receta es obligatorio.',
            'recipes.*.supply_id.exists' => 'El insumo seleccionado no existe.',
            'recipes.*.required_quantity.required_with' => 'La cantidad del insumo es obligatoria.',
            'recipes.*.required_quantity.numeric' => 'La cantidad del insumo debe ser numérica.',
            'recipes.*.required_quantity.gt' => 'La cantidad del insumo debe ser mayor a 0.',
            'complements.*.exists' => 'El complemento seleccionado no existe.',
        ];
    }
}

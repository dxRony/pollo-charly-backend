<?php

declare(strict_types=1);

namespace App\Http\Requests\DailyMenu;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SyncDailyMenuBatchRequest',
    title: 'Sync Daily Menu Batch Request',
    description: 'Lista de IDs de platillos que conformarán la selección destacada en el menú del día de la landing page',
    required: ['dish_ids'],
    example: [
        'dish_ids' => [1, 2, 4],
    ],
    properties: [
        new OA\Property(
            property: 'dish_ids',
            description: 'Arreglo de identificadores de platillos a publicar como menú del día (un arreglo vacío retirará todos los platillos)',
            type: 'array',
            items: new OA\Items(type: 'integer', example: 1)
        ),
    ]
)]
class SyncDailyMenuBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'dish_ids' => ['present', 'array'],
            'dish_ids.*' => ['integer', 'exists:dishes,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dish_ids.present' => 'El campo dish_ids debe estar presente.',
            'dish_ids.array' => 'El campo dish_ids debe ser un arreglo de IDs.',
            'dish_ids.*.integer' => 'Cada identificador de platillo debe ser un número entero.',
            'dish_ids.*.exists' => 'Uno o más platillos seleccionados no existen en el catálogo.',
        ];
    }
}

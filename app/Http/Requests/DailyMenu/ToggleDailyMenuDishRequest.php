<?php

declare(strict_types=1);

namespace App\Http\Requests\DailyMenu;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ToggleDailyMenuDishRequest',
    title: 'Toggle Daily Menu Dish Request',
    description: 'Datos para incluir o retirar un platillo específico del menú del día',
    required: ['is_daily_menu'],
    example: [
        'is_daily_menu' => true,
    ],
    properties: [
        new OA\Property(property: 'is_daily_menu', description: 'Indica si se destaca o no el platillo en el menú del día', type: 'boolean', example: true),
    ]
)]
class ToggleDailyMenuDishRequest extends FormRequest
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
            'is_daily_menu' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_daily_menu.required' => 'El indicador de menú del día es obligatorio.',
            'is_daily_menu.boolean' => 'El valor de menú del día debe ser verdadero o falso.',
        ];
    }
}

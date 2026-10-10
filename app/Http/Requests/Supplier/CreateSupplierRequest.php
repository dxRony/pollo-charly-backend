<?php

declare(strict_types=1);

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateSupplierRequest',
    title: 'Create Supplier Request',
    description: 'Datos necesarios para registrar un nuevo proveedor en el sistema',
    required: ['company_name', 'delivery_day_ids'],
    example: [
        'company_name' => 'Distribuidora Avícola',
        'contact_name' => 'Juan Pérez',
        'phone' => '555-1234',
        'email' => 'contacto@avicola.com',
        'address' => 'Av. Industrial 456',
        'is_active' => true,
        'delivery_day_ids' => [1, 3, 5],
        'supplies' => [
            [
                'supply_id' => 1,
                'agreed_price' => 32.50,
            ],
        ],
    ],
    properties: [
        new OA\Property(property: 'company_name', description: 'Razón social o nombre comercial del proveedor', type: 'string', maxLength: 150, example: 'Distribuidora Avícola'),
        new OA\Property(property: 'contact_name', description: 'Nombre de la persona de contacto', type: 'string', maxLength: 150, nullable: true, example: 'Juan Pérez'),
        new OA\Property(property: 'phone', description: 'Teléfono de contacto', type: 'string', maxLength: 50, nullable: true, example: '555-1234'),
        new OA\Property(property: 'email', description: 'Correo electrónico de contacto', type: 'string', format: 'email', maxLength: 150, nullable: true, example: 'contacto@avicola.com'),
        new OA\Property(property: 'address', description: 'Dirección física o fiscal', type: 'string', nullable: true, example: 'Av. Industrial 456'),
        new OA\Property(property: 'is_active', description: 'Estado activo inicial', type: 'boolean', example: true),
        new OA\Property(property: 'delivery_day_ids', description: 'Array de IDs de días de entrega asignados', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 3, 5]),
        new OA\Property(
            property: 'supplies',
            description: 'Listado de insumos provistos con precio pactado',
            type: 'array',
            items: new OA\Items(
                type: 'object',
                required: ['supply_id', 'agreed_price'],
                properties: [
                    new OA\Property(property: 'supply_id', type: 'integer', example: 1),
                    new OA\Property(property: 'agreed_price', type: 'number', format: 'float', example: 32.50),
                ]
            )
        ),
    ]
)]
class CreateSupplierRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'delivery_day_ids' => ['required', 'array', 'min:1'],
            'delivery_day_ids.*' => ['integer', 'exists:delivery_days,id'],
            'supplies' => ['sometimes', 'array'],
            'supplies.*.supply_id' => ['required_with:supplies', 'integer', 'exists:supplies,id'],
            'supplies.*.agreed_price' => ['required_with:supplies', 'numeric', 'min:0'],
        ];
    }

    /**
     * Mensajes personalizados de error en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'El nombre de la empresa o razón social es obligatorio.',
            'company_name.string' => 'El nombre de la empresa debe ser una cadena de texto.',
            'company_name.max' => 'El nombre de la empresa no puede superar los 150 caracteres.',
            'contact_name.string' => 'El nombre de contacto debe ser una cadena de texto.',
            'contact_name.max' => 'El nombre de contacto no puede superar los 150 caracteres.',
            'phone.string' => 'El teléfono debe ser una cadena de texto válida.',
            'phone.max' => 'El teléfono no puede superar los 50 caracteres.',
            'email.email' => 'El formato del correo electrónico ingresado no es válido.',
            'email.max' => 'El correo electrónico no puede superar los 150 caracteres.',
            'delivery_day_ids.required' => 'Debe seleccionar al menos un día de entrega.',
            'delivery_day_ids.array' => 'Los días de entrega deben ser un listado.',
            'delivery_day_ids.min' => 'Debe seleccionar al menos un día de entrega.',
            'delivery_day_ids.*.integer' => 'El identificador del día de entrega debe ser un número entero.',
            'delivery_day_ids.*.exists' => 'Uno de los días de entrega seleccionados no es válido.',
            'supplies.array' => 'La lista de insumos debe ser un arreglo.',
            'supplies.*.supply_id.required_with' => 'El identificador del insumo es obligatorio al incluir productos.',
            'supplies.*.supply_id.integer' => 'El identificador del insumo debe ser un número entero.',
            'supplies.*.supply_id.exists' => 'Uno de los insumos seleccionados no existe en el catálogo.',
            'supplies.*.agreed_price.required_with' => 'El precio acordado es obligatorio para cada insumo.',
            'supplies.*.agreed_price.numeric' => 'El precio acordado debe ser un valor numérico.',
            'supplies.*.agreed_price.min' => 'El precio acordado debe ser mayor o igual a 0.',
        ];
    }
}

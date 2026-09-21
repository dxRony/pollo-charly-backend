<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Models\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateOrderComplementItem',
    title: 'Create Order Complement Item',
    description: 'Complemento a añadir a un platillo de la comanda',
    required: ['complement_id', 'quantity'],
    properties: [
        new OA\Property(property: 'complement_id', description: 'ID del complemento en catálogo', type: 'integer', example: 3),
        new OA\Property(property: 'quantity', description: 'Cantidad del complemento', type: 'integer', minimum: 1, example: 1),
    ]
)]
#[OA\Schema(
    schema: 'CreateOrderItem',
    title: 'Create Order Item',
    description: 'Platillo a incluir en la comanda con sus complementos opcionales',
    required: ['dish_id', 'quantity'],
    properties: [
        new OA\Property(property: 'dish_id', description: 'ID del platillo en catálogo', type: 'integer', example: 1),
        new OA\Property(property: 'quantity', description: 'Cantidad de porciones del platillo', type: 'integer', minimum: 1, example: 2),
        new OA\Property(property: 'notes', description: 'Observaciones de preparación específicas para este platillo', type: 'string', maxLength: 500, nullable: true, example: 'Sin cebolla'),
        new OA\Property(property: 'complements', type: 'array', items: new OA\Items(ref: '#/components/schemas/CreateOrderComplementItem'), nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'CreateOrderRequest',
    title: 'Create Order Request',
    description: 'Datos requeridos para tomar y enviar una comanda a cocina',
    required: ['items'],
    example: [
        'order_type' => 'en_mesa',
        'restaurant_table_id' => 1,
        'notes' => 'Cliente solicita servicio rápido',
        'items' => [
            [
                'dish_id' => 1,
                'quantity' => 2,
                'notes' => 'Bien dorado',
                'complements' => [
                    [
                        'complement_id' => 2,
                        'quantity' => 2,
                    ]
                ]
            ]
        ]
    ],
    properties: [
        new OA\Property(property: 'order_type', description: 'Tipo de pedido: en_mesa o para_llevar', type: 'string', example: 'en_mesa', nullable: true),
        new OA\Property(property: 'order_type_id', description: 'ID del tipo de pedido en base de datos', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'restaurant_table_id', description: 'ID de la mesa libre (obligatorio para pedidos en mesa)', type: 'integer', nullable: true, example: 4),
        new OA\Property(property: 'notes', description: 'Observaciones generales para la comanda', type: 'string', maxLength: 1000, nullable: true, example: 'Mesa 4 comensales'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CreateOrderItem')),
    ]
)]
class CreateOrderRequest extends FormRequest
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
            'order_type' => ['nullable', 'string', 'in:en_mesa,para_llevar'],
            'order_type_id' => ['nullable', 'integer', 'exists:order_types,id'],
            'restaurant_table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.dish_id' => ['required', 'integer', 'exists:dishes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.complements' => ['nullable', 'array'],
            'items.*.complements.*.complement_id' => ['required_with:items.*.complements', 'integer', 'exists:complements,id'],
            'items.*.complements.*.quantity' => ['required_with:items.*.complements', 'integer', 'min:1'],
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
            'order_type.in' => 'El tipo de pedido debe ser "en_mesa" o "para_llevar".',
            'order_type_id.exists' => 'El tipo de pedido seleccionado no es válido.',
            'restaurant_table_id.exists' => 'La mesa seleccionada no existe en el registro del restaurante.',
            'notes.max' => 'Las notas generales no pueden exceder los 1000 caracteres.',
            'items.required' => 'Debe incluir al menos un platillo en la comanda.',
            'items.array' => 'El detalle de platillos debe ser una lista.',
            'items.min' => 'Debe incluir al menos un platillo en la comanda.',
            'items.*.dish_id.required' => 'El platillo es obligatorio.',
            'items.*.dish_id.exists' => 'El platillo seleccionado no existe en el catálogo.',
            'items.*.quantity.required' => 'La cantidad del platillo es obligatoria.',
            'items.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min' => 'La cantidad de cada platillo debe ser de al menos 1.',
            'items.*.notes.max' => 'Las observaciones del platillo no pueden superar los 500 caracteres.',
            'items.*.complements.*.complement_id.required_with' => 'El complemento seleccionado es obligatorio.',
            'items.*.complements.*.complement_id.exists' => 'El complemento seleccionado no existe en el catálogo.',
            'items.*.complements.*.quantity.required_with' => 'La cantidad del complemento es obligatoria.',
            'items.*.complements.*.quantity.min' => 'La cantidad de cada complemento debe ser de al menos 1.',
        ];
    }

    /**
     * Validación condicional según el tipo de pedido (en mesa o para llevar).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $resolvedTypeName = $this->getResolvedOrderTypeName();

            if (! $resolvedTypeName) {
                $v->errors()->add('order_type', 'Debe especificar el tipo de comanda (en_mesa o para_llevar).');
                return;
            }

            if ($resolvedTypeName === OrderType::EN_MESA) {
                if (empty($this->input('restaurant_table_id'))) {
                    $v->errors()->add('restaurant_table_id', 'Debe seleccionar una mesa libre para pedidos de tipo "en mesa".');
                }
            }
        });
    }

    /**
     * Obtiene el nombre canónico del tipo de pedido.
     */
    public function getResolvedOrderTypeName(): ?string
    {
        $orderType = $this->input('order_type');
        if (! empty($orderType)) {
            return $orderType;
        }

        $orderTypeId = $this->input('order_type_id');
        if (! empty($orderTypeId)) {
            $type = OrderType::query()->find($orderTypeId);
            return $type?->name;
        }

        return null;
    }
}

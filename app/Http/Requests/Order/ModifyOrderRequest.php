<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ModifyOrderRequest',
    title: 'Modify Order Request',
    description: 'Datos para modificar una comanda en curso: eliminar platillos permitidos, agregar nuevos productos y actualizar notas',
    properties: [
        new OA\Property(
            property: 'notes',
            description: 'Nuevas observaciones o notas actualizadas para la comanda',
            type: 'string',
            maxLength: 1000,
            nullable: true,
            example: 'Cliente solicita agregar salsa extra picante'
        ),
        new OA\Property(
            property: 'remove_item_ids',
            description: 'Lista de IDs de ítems de la comanda (order_items) a marcar como eliminados (bloqueado si está en preparación o fuera de tiempo)',
            type: 'array',
            items: new OA\Items(type: 'integer', example: 1),
            nullable: true
        ),
        new OA\Property(
            property: 'add_items',
            description: 'Lista de nuevos platillos y complementos a incorporar a la comanda',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CreateOrderItem'),
            nullable: true
        ),
    ]
)]
class ModifyOrderRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene permisos para modificar comandas.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::ADMINISTRADOR, Role::MESERO_CAJERO) ?? false;
    }

    /**
     * Respuesta personalizada en español cuando el usuario no tiene permisos.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para modificar comandas. Se requiere rol de Mesero/Cajero o Administrador.',
        ], 403));
    }

    /**
     * Reglas de validación aplicadas a la solicitud de modificación.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'remove_item_ids' => ['nullable', 'array'],
            'remove_item_ids.*' => ['integer', 'exists:order_items,id'],
            'add_items' => ['nullable', 'array'],
            'add_items.*.dish_id' => ['required_with:add_items', 'integer', 'exists:dishes,id'],
            'add_items.*.quantity' => ['required_with:add_items', 'integer', 'min:1'],
            'add_items.*.notes' => ['nullable', 'string', 'max:500'],
            'add_items.*.complements' => ['nullable', 'array'],
            'add_items.*.complements.*.complement_id' => ['required_with:add_items.*.complements', 'integer', 'exists:complements,id'],
            'add_items.*.complements.*.quantity' => ['required_with:add_items.*.complements', 'integer', 'min:1'],
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.max' => 'Las observaciones no pueden exceder los 1000 caracteres.',
            'remove_item_ids.array' => 'La lista de ítems a eliminar debe ser un arreglo.',
            'remove_item_ids.*.integer' => 'El identificador de cada ítem a eliminar debe ser un entero.',
            'remove_item_ids.*.exists' => 'Uno o más ítems a eliminar no existen en el registro de la comanda.',
            'add_items.array' => 'La lista de productos a agregar debe ser un arreglo.',
            'add_items.*.dish_id.required_with' => 'El platillo a agregar es obligatorio.',
            'add_items.*.dish_id.exists' => 'El platillo seleccionado no existe en el catálogo.',
            'add_items.*.quantity.required_with' => 'La cantidad del platillo es obligatoria.',
            'add_items.*.quantity.min' => 'La cantidad de cada platillo debe ser de al menos 1.',
            'add_items.*.notes.max' => 'Las notas de preparación del platillo no pueden superar los 500 caracteres.',
            'add_items.*.complements.*.complement_id.required_with' => 'El complemento seleccionado es obligatorio.',
            'add_items.*.complements.*.complement_id.exists' => 'El complemento seleccionado no existe en el catálogo.',
            'add_items.*.complements.*.quantity.required_with' => 'La cantidad del complemento es obligatoria.',
            'add_items.*.complements.*.quantity.min' => 'La cantidad de cada complemento debe ser de al menos 1.',
        ];
    }

    /**
     * Valida que se haya enviado al menos un cambio a realizar.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasNotes = $this->filled('notes');
            $hasRemovals = ! empty($this->input('remove_item_ids'));
            $hasAdditions = ! empty($this->input('add_items'));

            if (! $hasNotes && ! $hasRemovals && ! $hasAdditions) {
                $v->errors()->add('general', 'Debe especificar al menos una modificación: agregar platillos (add_items), eliminar platillos (remove_item_ids) o actualizar notas (notes).');
            }
        });
    }
}

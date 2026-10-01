<?php

declare(strict_types=1);

namespace App\Http\Requests\SupplyAlert;

use App\Models\Supply;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateSupplyAlertRequest',
    title: 'Create Supply Alert Request',
    description: 'Datos necesarios para generar manualmente una alerta de reposición de insumo',
    required: ['supply_id'],
    example: [
        'supply_id' => 1,
        'notes' => 'El insumo se está agotando más rápido de lo habitual en turno de cocina.',
    ],
    properties: [
        new OA\Property(property: 'supply_id', description: 'ID del producto o insumo del catálogo', type: 'integer', example: 1),
        new OA\Property(property: 'notes', description: 'Observación o motivo de la alerta manual', type: 'string', maxLength: 1000, nullable: true, example: 'Quedan pocas unidades físicas en bodega'),
    ]
)]
class CreateSupplyAlertRequest extends FormRequest
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
            'supply_id' => ['required', 'integer', 'exists:supplies,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'supply_id.required' => 'Debe seleccionar un producto o insumo del catálogo.',
            'supply_id.integer' => 'El identificador del insumo debe ser un número entero.',
            'supply_id.exists' => 'El producto o insumo seleccionado no existe en el catálogo. Por favor seleccione un producto válido.',
            'notes.string' => 'Las observaciones deben ser una cadena de texto.',
            'notes.max' => 'Las observaciones no pueden exceder los 1000 caracteres.',
        ];
    }

    /**
     * Validación complementaria para comprobar que el insumo esté activo.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $supplyId = $this->input('supply_id');
            if ($supplyId && is_numeric($supplyId)) {
                $supply = Supply::query()->find($supplyId);
                if ($supply && ! $supply->is_active) {
                    $v->errors()->add('supply_id', 'No se puede generar una alerta para un producto o insumo inactivo.');
                }
            }
        });
    }
}

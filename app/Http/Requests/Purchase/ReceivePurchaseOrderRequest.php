<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ReceivePurchaseOrderRequest',
    title: 'Receive Purchase Order Request',
    description: 'Datos para confirmar la recepción de una compra a proveedor',
    example: [
        'received_date' => '2026-10-15',
        'notes' => 'Mercancía recibida completa y en perfecto estado.',
    ],
    properties: [
        new OA\Property(property: 'received_date', description: 'Fecha efectiva de recepción (YYYY-MM-DD)', type: 'string', format: 'date', nullable: true, example: '2026-10-15'),
        new OA\Property(property: 'notes', description: 'Notas u observaciones de recepción', type: 'string', maxLength: 1000, nullable: true, example: 'Recepción conforme en bodega'),
    ]
)]
class ReceivePurchaseOrderRequest extends FormRequest
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
            'received_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'resolve_incidents' => ['nullable', 'boolean'],
            'items' => ['nullable', 'array'],
            'items.*.supply_id' => ['required_with:items', 'integer', 'exists:supplies,id'],
            'items.*.received_quantity' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'received_date.date' => 'La fecha de recepción debe ser una fecha válida.',
            'notes.max' => 'Las notas de recepción no pueden exceder los 1000 caracteres.',
        ];
    }
}

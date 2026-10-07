<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RejectPurchaseRequestRequest',
    title: 'Reject Purchase Request Request',
    description: 'Datos opcionales para rechazar una solicitud de compra sin generar orden',
    example: [
        'reason' => 'Se verificó suficiente stock disponible en almacén secundario.',
    ],
    properties: [
        new OA\Property(property: 'reason', description: 'Motivo u observación del rechazo', type: 'string', maxLength: 1000, nullable: true, example: 'No se requiere compra en este momento.'),
    ]
)]
class RejectPurchaseRequestRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'El motivo de rechazo no puede exceder los 1000 caracteres.',
        ];
    }
}

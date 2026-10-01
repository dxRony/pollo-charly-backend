<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CancelOrderRequest',
    title: 'Cancel Order Request',
    description: 'Datos para anular una comanda antes de que cocina inicie su preparación',
    properties: [
        new OA\Property(
            property: 'cancellation_reason',
            description: 'Motivo o justificación de la cancelación de la comanda',
            type: 'string',
            maxLength: 1000,
            nullable: true,
            example: 'El cliente cambió de opinión y solicita anular el pedido antes de preparación'
        ),
        new OA\Property(
            property: 'reason',
            description: 'Alias alternativo para el motivo de cancelación',
            type: 'string',
            maxLength: 1000,
            nullable: true,
            example: 'Cliente decidió retirarse del restaurante'
        ),
    ]
)]
class CancelOrderRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene permisos para cancelar comandas.
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
            'message' => 'No tienes permisos para cancelar comandas. Se requiere rol de Mesero/Cajero o Administrador.',
        ], 403));
    }

    /**
     * Reglas de validación aplicadas a la solicitud de cancelación.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'cancellation_reason.max' => 'El motivo de cancelación no puede exceder los 1000 caracteres.',
            'reason.max' => 'El motivo de cancelación no puede exceder los 1000 caracteres.',
        ];
    }

    /**
     * Obtiene el motivo de cancelación ingresado o un texto descriptivo por defecto.
     */
    public function getResolvedReason(): string
    {
        $reason = $this->input('cancellation_reason') ?? $this->input('reason');

        if (! empty($reason)) {
            return trim((string) $reason);
        }

        return 'Cancelación solicitada por el cliente antes de preparación.';
    }
}

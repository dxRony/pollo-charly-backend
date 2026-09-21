<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Models\OrderStatus;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateOrderStatusRequest',
    title: 'Update Order Status Request',
    description: 'Datos requeridos para actualizar el estado del ciclo de vida de una comanda',
    required: ['status'],
    properties: [
        new OA\Property(
            property: 'status',
            description: 'Nombre clave del nuevo estado deseado: en_preparacion, lista o entregada',
            type: 'string',
            enum: ['en_preparacion', 'lista', 'entregada'],
            example: 'en_preparacion'
        ),
        new OA\Property(
            property: 'order_status_id',
            description: 'Identificador del estado en la base de datos (alternativa a status)',
            type: 'integer',
            nullable: true,
            example: 2
        ),
    ]
)]
class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Determina si el usuario autenticado tiene permisos para cambiar el estado de la comanda.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(Role::COCINERO, Role::MESERO_CAJERO, Role::ADMINISTRADOR) ?? false;
    }

    /**
     * Respuesta personalizada en español cuando el usuario no tiene permisos.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'No tienes permisos para cambiar el estado de la comanda. Se requiere rol de Cocinero, Mesero/Cajero o Administrador.',
        ], 403));
    }

    /**
     * Reglas de validación aplicadas a la solicitud de cambio de estado.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required_without:order_status_id',
                'string',
                'in:en_preparacion,lista,entregada',
            ],
            'order_status_id' => [
                'required_without:status',
                'integer',
                'exists:order_statuses,id',
            ],
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
            'status.required_without' => 'Debe indicar el nuevo estado de la comanda (status o order_status_id).',
            'status.in' => 'El estado indicado no es válido. Los estados de avance permitidos son: en_preparacion, lista o entregada.',
            'order_status_id.required_without' => 'Debe indicar el identificador del nuevo estado de la comanda.',
            'order_status_id.exists' => 'El estado de comanda seleccionado no existe en el catálogo.',
        ];
    }

    /**
     * Obtiene el nombre resuelto del nuevo estado solicitado.
     */
    public function getResolvedStatusName(): ?string
    {
        $status = $this->input('status');
        if (! empty($status)) {
            return (string) $status;
        }

        $orderStatusId = $this->input('order_status_id');
        if (! empty($orderStatusId)) {
            $record = OrderStatus::query()->find($orderStatusId);
            return $record?->name;
        }

        return null;
    }
}

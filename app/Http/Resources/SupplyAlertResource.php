<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SupplyAlertResource',
    title: 'Supply Alert Resource',
    description: 'Información completa de una alerta de reposición de insumo',
    properties: [
        new OA\Property(property: 'id', description: 'Identificador único de la alerta', type: 'integer', example: 1),
        new OA\Property(property: 'supply_id', description: 'ID del producto o insumo relacionado', type: 'integer', example: 1),
        new OA\Property(property: 'supply', ref: '#/components/schemas/SupplyResource', nullable: true),
        new OA\Property(property: 'alert_origin_id', description: 'ID del origen de la alerta', type: 'integer', example: 1),
        new OA\Property(property: 'origin', ref: '#/components/schemas/AlertOriginResource', nullable: true),
        new OA\Property(property: 'origin_name', description: 'Nombre clave del origen (manual o automatic)', type: 'string', example: 'automatic'),
        new OA\Property(property: 'alert_status_id', description: 'ID del estado de la alerta', type: 'integer', example: 1),
        new OA\Property(property: 'status', ref: '#/components/schemas/AlertStatusResource', nullable: true),
        new OA\Property(property: 'status_name', description: 'Nombre clave del estado (pending o attended)', type: 'string', example: 'pending'),
        new OA\Property(property: 'user_id', description: 'ID del usuario que originó o registró la alerta', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'user', description: 'Información básica del usuario responsable', type: 'object', nullable: true),
        new OA\Property(property: 'purchase_request_id', description: 'ID de la solicitud de compra vinculada al atender (si aplica)', type: 'integer', nullable: true),
        new OA\Property(property: 'notes', description: 'Observaciones o motivo de la alerta', type: 'string', nullable: true, example: 'El insumo se está agotando rápidamente en turno'),
        new OA\Property(property: 'created_at', description: 'Fecha y hora de generación de la alerta', type: 'string', format: 'date-time', example: '2026-09-20T12:00:00.000000Z'),
        new OA\Property(property: 'updated_at', description: 'Fecha y hora de última actualización', type: 'string', format: 'date-time', example: '2026-09-20T12:00:00.000000Z'),
    ]
)]
class SupplyAlertResource extends JsonResource
{
    /**
     * Transforma la alerta a un arreglo estructurado para la respuesta JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supply_id' => $this->supply_id,
            'supply' => $this->relationLoaded('supply') && $this->supply
                ? new SupplyResource($this->supply)
                : null,
            'alert_origin_id' => $this->alert_origin_id,
            'origin' => $this->relationLoaded('origin') && $this->origin
                ? new AlertOriginResource($this->origin)
                : null,
            'origin_name' => $this->relationLoaded('origin') && $this->origin
                ? $this->origin->name
                : null,
            'alert_status_id' => $this->alert_status_id,
            'status' => $this->relationLoaded('status') && $this->status
                ? new AlertStatusResource($this->status)
                : null,
            'status_name' => $this->relationLoaded('status') && $this->status
                ? $this->status->name
                : null,
            'user_id' => $this->user_id,
            'user' => $this->relationLoaded('user') && $this->user
                ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'role' => $this->user->relationLoaded('role') && $this->user->role
                        ? $this->user->role->name
                        : null,
                ]
                : null,
            'purchase_request_id' => $this->purchase_request_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
